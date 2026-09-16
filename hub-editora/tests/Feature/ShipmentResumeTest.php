<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Integrations\MelhorEnvio\ShipmentService;
use App\Models\Channel;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use Database\Seeders\ChannelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShipmentResumeTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://me.test/api/v2';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChannelSeeder::class);
        config(['hub.melhor_envio.token' => 'tok', 'hub.melhor_envio.base_url' => self::BASE, 'hub.melhor_envio.from.postal_code' => '74610060']);
    }

    private function problemShipment(): Shipment
    {
        $customer = Customer::create(['name' => 'Cliente', 'document' => '12345678909']);
        $order = Order::create([
            'channel_id' => Channel::bySlug(Channel::AMAZON)->id, 'external_id' => '1', 'external_number' => '701-1', 'customer_id' => $customer->id,
            'status' => OrderStatus::Problem, 'payment_status' => 'paid', 'requires_shipping' => true, 'placed_at' => now(),
            'ship_street' => 'Rua A', 'ship_number' => '1', 'ship_city' => 'Goiânia', 'ship_state' => 'GO', 'ship_zip' => '74000000',
        ]);

        return Shipment::create([
            'order_id' => $order->id, 'status' => Shipment::PROBLEM, 'carrier' => 'Correios', 'service' => 'PAC',
            'cost_cents' => 3443, 'melhor_envio_id' => 'me-1', 'problem' => 'checkout (422): saldo insuficiente',
        ]);
    }

    public function test_resumes_a_shipment_paid_and_generated_in_melhor_envio(): void
    {
        $shipment = $this->problemShipment();

        Http::fake([
            self::BASE.'/me/shipment/tracking' => Http::response(['me-1' => ['status' => 'released', 'paid_at' => '2026-09-16 13:57:39', 'generated_at' => '2026-09-16 14:02:23', 'tracking' => 'AP502354781BR']]),
            self::BASE.'/me/shipment/print' => Http::response(['url' => 'https://me.test/etiqueta.pdf']),
        ]);

        $s = app(ShipmentService::class)->resume($shipment);

        $this->assertSame(Shipment::LABEL_GENERATED, $s->status);
        $this->assertSame('AP502354781BR', $s->tracking_code);
        $this->assertSame('https://me.test/etiqueta.pdf', $s->label_url);
        $this->assertNull($s->problem);
        $this->assertSame(OrderStatus::LabelGenerated, $s->order->fresh()->status);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/checkout') || str_contains($r->url(), '/generate') || str_contains($r->url(), '/cart'));
    }

    public function test_buy_does_not_duplicate_a_shipment_already_in_the_cart(): void
    {
        $shipment = $this->problemShipment();

        Http::fake([
            self::BASE.'/me/shipment/tracking' => Http::response(['me-1' => ['status' => 'pending', 'paid_at' => null, 'generated_at' => null]]),
            self::BASE.'/me/shipment/checkout' => Http::response(['message' => 'Seu saldo de R$ 0.00 é insuficiente'], 422),
        ]);

        $s = app(ShipmentService::class)->buy($shipment->order);

        $this->assertSame(Shipment::PROBLEM, $s->status);
        $this->assertSame('me-1', $s->melhor_envio_id);
        $this->assertSame(1, Shipment::count());
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/me/cart'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/checkout'));
    }
}
