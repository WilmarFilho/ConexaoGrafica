<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Channel;
use App\Models\Customer;
use App\Models\Order;
use App\Models\SyncLog;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChangeOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_status_change_from_the_orders_list_locks_the_order_and_is_audited(): void
    {
        $this->seed(ChannelSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $customer = Customer::create(['name' => 'Breno']);
        $order = Order::create([
            'channel_id' => Channel::bySlug(Channel::AMAZON)->id, 'external_id' => '9', 'external_number' => '701-2887165-6882661',
            'customer_id' => $customer->id, 'status' => OrderStatus::Paid, 'payment_status' => 'paid', 'requires_shipping' => true, 'placed_at' => now(),
        ]);

        Livewire::test(ListOrders::class)
            ->callTableAction('alterar_etapa', $order, data: ['status' => 'shipped', 'reason' => 'Despachado via Correios', 'lock' => true])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Etapa atualizada');

        $order->refresh();
        $this->assertSame(OrderStatus::Shipped, $order->status);
        $this->assertTrue($order->status_manual);

        $log = SyncLog::where('action', 'status.changed')->firstOrFail();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(SyncLog::MANUAL, $log->direction);
        $this->assertStringContainsString('Despachado via Correios', $log->message);
        $this->assertSame(['from' => 'paid', 'to' => 'shipped', 'locked' => true, 'reason' => 'Despachado via Correios'], $log->context);
    }
}
