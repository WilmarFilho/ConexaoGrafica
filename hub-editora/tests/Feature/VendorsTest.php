<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Expedicao;
use App\Filament\Pages\VendorPayoutsPortal;
use App\Filament\Pages\VendorPortal;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\VendorPayouts\Pages\ViewVendorPayout;
use App\Filament\Resources\VendorPayouts\VendorPayoutResource;
use App\Filament\Resources\Vendors\Pages\EditVendor;
use App\Filament\Resources\Vendors\Pages\ListVendors;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\Channel;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductChannelRef;
use App\Models\SyncLog;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCommission;
use App\Models\VendorPayout;
use App\Vendors\Commissions;
use App\Vendors\Payouts;
use Database\Seeders\ChannelSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/** Multivendor: comissões por pedido, repasses, portal do vendedor e acessos. */
class VendorsTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChannelSeeder::class);
    }

    private function vendor(array $attributes = []): Vendor
    {
        return Vendor::create(array_merge(['name' => 'Vânia Meira', 'email' => 'vania@example.com'], $attributes));
    }

    private function product(?Vendor $vendor, array $attributes = []): Product
    {
        $this->seq++;

        return Product::create(array_merge([
            'name' => 'Livro '.$this->seq, 'sku' => 'WOO-'.$this->seq, 'formats' => ['fisico'], 'active' => true, 'vendor_id' => $vendor?->id,
        ], $attributes));
    }

    /** Pedido do WooCommerce com itens [produto, qtd, total em centavos]. */
    private function order(array $items, string $payment = 'paid', OrderStatus $status = OrderStatus::Paid, ?string $placedAt = null): Order
    {
        $this->seq++;
        $order = Order::create([
            'channel_id' => Channel::bySlug(Channel::WOOCOMMERCE)->id, 'external_id' => (string) $this->seq, 'external_number' => '#'.(10000 + $this->seq),
            'status' => $status, 'payment_status' => $payment, 'requires_shipping' => true, 'placed_at' => $placedAt ?? now(),
        ]);
        foreach ($items as [$product, $qty, $total]) {
            $order->items()->create(['product_id' => $product->id, 'name' => $product->name, 'quantity' => $qty, 'unit_cents' => intdiv($total, $qty), 'total_cents' => $total]);
        }
        Commissions::make()->syncOrder($order);

        return $order;
    }

    private function login(string $role, ?Vendor $vendor = null): User
    {
        $user = User::factory()->create(['role' => $role, 'vendor_id' => $vendor?->id, 'app_authentication_secret' => 'segredo']);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $user;
    }

    // ---- Comissões ------------------------------------------------------------

    public function test_commission_follows_the_order_with_vendor_and_product_rates(): void
    {
        $vania = $this->vendor();
        $padrao = $this->product($vania);
        $proprio = $this->product($vania, ['vendor_commission_pct' => 80]);
        $editora = $this->product(null);

        $order = $this->order([[$padrao, 2, 10000], [$padrao, 1, 5000], [$proprio, 1, 3999], [$editora, 1, 7000]]);

        $rows = VendorCommission::query()->where('order_id', $order->id)->orderBy('product_id')->get();
        $this->assertCount(2, $rows, 'uma linha por livro do vendedor; o livro da editora não entra');
        $this->assertSame([3, 15000, 9750, 'available'], [$rows[0]->quantity, $rows[0]->base_cents, $rows[0]->amount_cents, $rows[0]->status]);
        $this->assertSame([3999, 3199], [$rows[1]->base_cents, $rows[1]->amount_cents]); // 80% arredondado
        $this->assertSame(12949, $vania->availableCents());

        // Reimportar (itens recriados) não duplica.
        $order->items()->delete();
        $order->items()->create(['product_id' => $padrao->id, 'name' => 'x', 'quantity' => 1, 'unit_cents' => 5000, 'total_cents' => 5000]);
        Commissions::make()->syncOrder($order);
        $this->assertSame(1, VendorCommission::query()->where('order_id', $order->id)->count(), 'livro que saiu do pedido some');
        $this->assertSame(3250, $vania->availableCents());
    }

    public function test_commission_state_tracks_payment_and_cancellation(): void
    {
        $vania = $this->vendor();
        $livro = $this->product($vania);
        $order = $this->order([[$livro, 1, 10000]], payment: 'pending', status: OrderStatus::AwaitingPayment);

        $commission = VendorCommission::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(VendorCommission::PENDING, $commission->status);
        $this->assertSame(6500, $vania->pendingCents());

        $order->update(['payment_status' => 'paid', 'status' => OrderStatus::Paid]);
        $this->assertSame(VendorCommission::AVAILABLE, $commission->fresh()->status);

        $order->update(['status' => OrderStatus::Cancelled]);
        $this->assertSame(VendorCommission::CANCELLED, $commission->fresh()->status);
        $this->assertSame(0, $vania->availableCents());
    }

    public function test_orders_before_the_start_date_do_not_generate_commission(): void
    {
        $vania = $this->vendor(['commissions_start_at' => '2026-10-24']);
        $livro = $this->product($vania);

        $antes = $this->order([[$livro, 1, 10000]], placedAt: '2026-10-23 23:00:00');
        $depois = $this->order([[$livro, 1, 10000]], placedAt: '2026-10-24 08:00:00');

        $this->assertFalse(VendorCommission::query()->where('order_id', $antes->id)->exists());
        $this->assertTrue(VendorCommission::query()->where('order_id', $depois->id)->exists());

        // Mudar a data recalcula.
        $vania->update(['commissions_start_at' => '2026-10-01']);
        $this->assertTrue(VendorCommission::query()->where('order_id', $antes->id)->exists());
    }

    public function test_changing_the_rate_recalculates_only_what_was_not_paid_out(): void
    {
        $vania = $this->vendor();
        $livro = $this->product($vania);
        $pago = $this->order([[$livro, 1, 10000]], placedAt: now()->subDays(3)->toDateTimeString());
        $payout = Payouts::make()->create($vania, now()->subDays(2));
        Payouts::make()->markPaid($payout, now(), 'pix', 'E123');
        $aberto = $this->order([[$livro, 1, 10000]]);

        $livro->update(['vendor_commission_pct' => 50]);

        $this->assertSame(6500, VendorCommission::query()->where('order_id', $pago->id)->value('amount_cents'), 'repassada não muda');
        $this->assertSame(5000, VendorCommission::query()->where('order_id', $aberto->id)->value('amount_cents'));
    }

    // ---- Repasses -------------------------------------------------------------

    public function test_payout_lifecycle_and_reversal_after_payment(): void
    {
        $vania = $this->vendor();
        $livro = $this->product($vania);
        $a = $this->order([[$livro, 1, 10000]], placedAt: now()->subDays(5)->toDateTimeString());
        $this->order([[$livro, 1, 20000]], placedAt: now()->subDays(4)->toDateTimeString());
        $depois = $this->order([[$livro, 1, 30000]]); // fora do período

        $payout = Payouts::make()->create($vania, now()->subDays(1));
        $this->assertSame([VendorPayout::DRAFT, 19500, 2], [$payout->status, $payout->amount_cents, $payout->commissions_count]);
        $this->assertSame(19500, (int) $vania->commissions()->whereNull('payout_id')->where('status', 'available')->sum('amount_cents'), 'o pedido de hoje fica para o próximo');

        // Cancelar devolve as comissões; gerar de novo funciona.
        Payouts::make()->cancel($payout);
        $this->assertSame(0, VendorCommission::query()->whereNotNull('payout_id')->count());
        $payout = Payouts::make()->create($vania, now()->subDays(1));

        Payouts::make()->markPaid($payout, now(), 'pix', 'E2E-1');
        $this->assertSame(VendorPayout::PAID, $payout->fresh()->status);
        $this->assertSame(2, VendorCommission::query()->where('status', VendorCommission::PAID)->count());
        $this->assertTrue(SyncLog::query()->where('action', 'vendor.payout_paid')->exists());

        // Pedido já repassado cai: nasce estorno negativo que desconta no próximo repasse.
        $a->update(['status' => OrderStatus::Cancelled]);
        $reversal = VendorCommission::query()->where('order_id', $a->id)->where('kind', VendorCommission::REVERSAL)->firstOrFail();
        $this->assertSame([-6500, VendorCommission::AVAILABLE], [$reversal->amount_cents, $reversal->status]);
        $a->update(['status' => OrderStatus::Cancelled, 'payment_status' => 'refunded']); // de novo: um estorno só
        $this->assertSame(1, VendorCommission::query()->where('kind', VendorCommission::REVERSAL)->count());

        $next = Payouts::make()->create($vania, now());
        $this->assertSame(19500 - 6500, $next->amount_cents); // 30000×65% − estorno
        $this->assertTrue($depois->exists);

        // Nada a repassar.
        $this->expectException(RuntimeException::class);
        Payouts::make()->create($vania, now());
    }

    // ---- Painel -----------------------------------------------------------------

    public function test_admin_manages_vendors_payouts_and_links_books(): void
    {
        $vania = $this->vendor(['commission_pct' => 65]);
        $livro = $this->product(null);
        $this->order([[$livro, 1, 10000]], placedAt: now()->subDay()->toDateTimeString());
        $this->login(User::ROLE_ADMIN);

        // Ligar o livro ao vendedor pela tela do produto gera a comissão do pedido existente.
        Livewire::test(EditProduct::class, ['record' => $livro->getRouteKey()])
            ->fillForm(['vendor_id' => $vania->id])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(6500, $vania->availableCents());

        Livewire::test(ListVendors::class)->assertCanSeeTableRecords([$vania])->assertSee('R$ 65,00');

        Livewire::test(EditVendor::class, ['record' => $vania->getRouteKey()])
            ->assertSee('A repassar')
            ->callAction('gerar_repasse', data: ['until' => now()->toDateString()])
            ->assertNotified('Repasse gerado: R$ 65,00');

        $payout = VendorPayout::query()->firstOrFail();
        Livewire::test(ViewVendorPayout::class, ['record' => $payout->getRouteKey()])
            ->assertSee('Vânia Meira')
            ->callAction('marcar_pago', data: ['paid_at' => now()->toDateTimeString(), 'method' => 'pix', 'reference' => 'E2E-99'])
            ->assertNotified('Pagamento registrado');
        $this->assertSame(VendorPayout::PAID, $payout->fresh()->status);

        // Conta de vendedor exige o vendedor.
        Livewire::test(ListUsers::class)
            ->callAction('create', data: ['name' => 'Vânia', 'email' => 'v@example.com', 'role' => User::ROLE_VENDEDOR])
            ->assertHasActionErrors(['vendor_id' => 'required']);
    }

    public function test_vendor_account_sees_only_its_own_sales_and_no_staff_screens(): void
    {
        $vania = $this->vendor();
        $gilton = $this->vendor(['name' => 'Gilton Junior', 'email' => 'g@example.com']);
        $meu = $this->order([[$this->product($vania), 1, 10000]]);
        $outro = $this->order([[$this->product($gilton), 1, 10000]]);

        $this->login(User::ROLE_VENDEDOR, $vania);

        Livewire::test(VendorPortal::class)
            ->assertCanSeeTableRecords(VendorCommission::query()->where('order_id', $meu->id)->get())
            ->assertCanNotSeeTableRecords(VendorCommission::query()->where('order_id', $outro->id)->get())
            ->assertSee('Vânia Meira');
        Livewire::test(VendorPayoutsPortal::class)->assertSuccessful();

        foreach ([OrderResource::class, ProductResource::class, VendorResource::class, VendorPayoutResource::class] as $resource) {
            $this->assertFalse($resource::canAccess(), $resource);
        }
        $this->assertFalse(Expedicao::canAccess());
        $this->get(OrderResource::getUrl('index'))->assertForbidden();
        $this->get(Dashboard::getUrl())->assertRedirect(VendorPortal::getUrl());
        $this->get(VendorPortal::getUrl())->assertOk()->assertSee('Minhas vendas')->assertSee('Meus repasses')
            ->assertDontSee('Expedição')->assertDontSee('Auditoria')->assertDontSee('Integrações')->assertDontSee('Visão geral');

        // Equipe não vê o portal; vendedor sem vendedor ligado nem entra no painel.
        $this->login(User::ROLE_EXPEDICAO);
        $this->assertFalse(VendorPortal::canAccess());
        $orfao = User::factory()->create(['role' => User::ROLE_VENDEDOR]);
        $this->assertFalse($orfao->canAccessPanel(Filament::getPanel('admin')));
    }

    // ---- Importação do MVX ------------------------------------------------------

    public function test_vendors_are_imported_from_mvx_without_duplicating(): void
    {
        $woo = Channel::bySlug(Channel::WOOCOMMERCE);
        $livro = $this->product(null, ['name' => 'Lembre-se de quem você é']);
        ProductChannelRef::create(['product_id' => $livro->id, 'channel_id' => $woo->id, 'external_id' => '7612']);
        $this->order([[$livro, 1, 10000]], placedAt: '2026-10-20 10:00:00');
        $novo = $this->order([[$livro, 1, 10000]], placedAt: '2026-10-25 10:00:00');

        $file = tempnam(sys_get_temp_dir(), 'mvx');
        file_put_contents($file, json_encode([
            ['mvx_id' => 388, 'name' => 'Rafael Ferreira Daher', 'email' => 'r@example.com', 'commission_pct' => 65, 'products' => [['woo_id' => 7612, 'commission_pct' => 80], ['woo_id' => 999]]],
        ]));

        $this->artisan('hub:vendors-import-mvx', ['arquivo' => $file, '--desde' => '2026-10-24'])
            ->expectsOutputToContain('produto Woo 999 não está no catálogo')
            ->assertSuccessful();
        $this->artisan('hub:vendors-import-mvx', ['arquivo' => $file, '--desde' => '2026-10-24'])->assertSuccessful();

        $rafael = Vendor::query()->where('mvx_vendor_id', 388)->sole();
        $this->assertSame($rafael->id, $livro->fresh()->vendor_id);
        $this->assertSame('80.00', $livro->fresh()->vendor_commission_pct);
        $this->assertSame([$novo->id], VendorCommission::query()->pluck('order_id')->all(), 'só pedidos a partir de --desde');
        $this->assertSame(8000, $rafael->availableCents());
        @unlink($file);
    }
}
