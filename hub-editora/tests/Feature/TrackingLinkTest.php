<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Pages\Expedicao;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Channel;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TrackingLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_codigo_de_rastreio_vira_link_na_expedicao_e_no_pedido(): void
    {
        $this->seed(ChannelSeeder::class);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $order = Order::create([
            'channel_id' => Channel::bySlug(Channel::AMAZON)->id, 'external_id' => '10', 'external_number' => '701-0000000-0000001',
            'customer_id' => Customer::create(['name' => 'Breno'])->id, 'status' => OrderStatus::LabelGenerated,
            'payment_status' => 'paid', 'requires_shipping' => true, 'placed_at' => now(),
        ]);
        Shipment::create(['order_id' => $order->id, 'status' => Shipment::LABEL_GENERATED, 'tracking_code' => 'AP502354781BR']);

        $link = 'href="https://www.melhorrastreio.com.br/rastreio/AP502354781BR"';

        Livewire::test(Expedicao::class)->assertSeeHtml($link)->assertSeeHtml('target="_blank"');
        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->assertSeeHtml($link)->assertSee('Abrir rastreio');
    }
}
