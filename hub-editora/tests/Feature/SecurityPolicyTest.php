<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\SyncLog;
use App\Models\User;
use App\Notifications\SecurityAlert;
use App\Support\SecurityAudit;
use Database\Seeders\ChannelSeeder;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_policy_requires_12_chars_mixed_case_numbers_and_symbols(): void
    {
        $rule = fn (string $p) => Validator::make(['password' => $p], ['password' => \Illuminate\Validation\Rules\Password::default()])->passes();

        $this->assertFalse($rule('senha1234'));
        $this->assertFalse($rule('SenhaSemNumeros!!'));
        $this->assertFalse($rule('senhaminuscula12!'));
        $this->assertTrue($rule('Expedicao-Hub-2026!x'));
    }

    public function test_password_change_resets_the_clock_and_expired_users_are_sent_to_the_profile(): void
    {
        // MFA já configurado (obrigatório no painel); o que se testa aqui é a expiração da senha.
        $user = User::factory()->create(['password_changed_at' => now()->subDays(400), 'app_authentication_secret' => 'segredo-teste']);
        $this->assertTrue($user->passwordIsExpired());

        $this->actingAs($user)->get('/admin/orders')->assertRedirect(route('filament.admin.auth.profile'));

        $user->password = 'Nova-Senha-Forte-2026!';
        $user->save();
        $this->assertFalse($user->fresh()->passwordIsExpired());
        $this->actingAs($user->fresh())->get('/admin/orders')->assertOk();
    }

    public function test_logins_and_failures_are_audited_and_brute_force_alerts_once(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        event(new Login('web', $user, false));
        $rows = SyncLog::where('action', 'auth.login')->get();
        $this->assertCount(1, $rows, 'linhas: '.$rows->map(fn ($r) => $r->message.' ['.json_encode($r->context).']')->implode(' | '));

        for ($i = 0; $i < SecurityAudit::FAILED_THRESHOLD + 1; $i++) {
            event(new Failed('web', null, ['email' => 'x@y.com', 'password' => 'bad']));
        }

        $this->assertSame(SecurityAudit::FAILED_THRESHOLD + 1, SyncLog::where('action', 'auth.failed')->count());
        Notification::assertSentTimes(SecurityAlert::class, 1);
    }

    public function test_pii_purge_anonymizes_amazon_orders_shipped_over_30_days_ago(): void
    {
        $this->seed(ChannelSeeder::class);
        $amz = Channel::bySlug(Channel::AMAZON);
        $customer = Customer::create(['name' => 'Paulo Fernando', 'email' => 'p@x.com', 'phone' => '629', 'document' => '12345678909']);

        $old = Order::create(['channel_id' => $amz->id, 'external_id' => '1', 'external_number' => '701-1', 'customer_id' => $customer->id, 'status' => 'shipped', 'payment_status' => 'paid', 'requires_shipping' => true, 'placed_at' => now()->subDays(50),
            'ship_name' => 'Paulo', 'ship_street' => 'Av. Itacira', 'ship_number' => '1000', 'ship_city' => 'São Paulo', 'ship_state' => 'SP', 'ship_zip' => '04061001', 'raw' => ['contato' => ['nome' => 'Paulo'], 'transporte' => ['etiqueta' => ['cep' => '04061001'], 'frete' => 13.45]]]);
        Shipment::create(['order_id' => $old->id, 'status' => Shipment::SHIPPED, 'tracking_code' => 'AP1BR', 'shipped_at' => now()->subDays(40)]);

        $recent = Order::create(['channel_id' => $amz->id, 'external_id' => '2', 'external_number' => '701-2', 'customer_id' => $customer->id, 'status' => 'shipped', 'payment_status' => 'paid', 'requires_shipping' => true, 'placed_at' => now()->subDays(5), 'ship_street' => 'Rua B', 'ship_zip' => '74000000']);
        Shipment::create(['order_id' => $recent->id, 'status' => Shipment::SHIPPED, 'tracking_code' => 'AP2BR', 'shipped_at' => now()->subDays(3)]);

        $this->artisan('hub:purge-pii')->assertSuccessful();

        $old->refresh();
        $this->assertNotNull($old->pii_purged_at);
        $this->assertNull($old->ship_street);
        $this->assertNull($old->ship_zip);
        $this->assertSame('São Paulo', $old->ship_city);
        $this->assertSame('AP1BR', $old->shipment->tracking_code);
        $this->assertArrayNotHasKey('contato', $old->raw);
        $this->assertArrayNotHasKey('etiqueta', $old->raw['transporte']);

        // Cliente ainda tem pedido recente: nome preservado por enquanto.
        $this->assertSame('Paulo Fernando', $customer->fresh()->name);
        $this->assertNull($recent->fresh()->pii_purged_at);
        $this->assertSame(1, SyncLog::where('action', 'pii.purged')->count());
    }

    public function test_privacy_policy_is_public(): void
    {
        $this->get('/privacidade')->assertOk()->assertSee('Política de Privacidade')->assertSee('30 dias');
    }
}
