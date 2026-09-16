<?php

namespace Tests\Feature;

use App\Integrations\Amazon\AmazonClient;
use App\Jobs\NotifyChannelShipped;
use App\Models\Channel;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\SyncLog;
use Database\Seeders\ChannelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AmazonConfirmShipmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChannelSeeder::class);
        config(['hub.amazon' => [
            'client_id' => 'amzn1.application-oa2-client.x', 'client_secret' => 'sec', 'refresh_token' => 'Atzr|x',
            'marketplace_id' => 'A2Q3Y263D00KWC', 'endpoint' => 'https://sp.test', 'token_url' => 'https://lwa.test/token',
        ]]);
        cache()->flush();
    }

    public function test_confirms_shipment_with_tracking_and_all_items(): void
    {
        Http::fake([
            'lwa.test/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'sp.test/orders/v0/orders/702-1/orderItems' => Http::response(['payload' => ['OrderItems' => [['OrderItemId' => 'it-1', 'QuantityOrdered' => 1]]]]),
            'sp.test/orders/v0/orders/702-1/shipmentConfirmation' => Http::response('', 204),
        ]);

        AmazonClient::fromConfig()->confirmShipment('702-1', 'AP502354720BR', 'Correios', 'PAC', now());

        Http::assertSent(function ($req) {
            if (! str_ends_with($req->url(), '/shipmentConfirmation')) {
                return false;
            }
            $d = $req->data();

            return $req->hasHeader('x-amz-access-token', 'tok')
                && $d['marketplaceId'] === 'A2Q3Y263D00KWC'
                && $d['packageDetail']['carrierCode'] === 'Correios'
                && $d['packageDetail']['trackingNumber'] === 'AP502354720BR'
                && $d['packageDetail']['orderItems'] === [['orderItemId' => 'it-1', 'quantity' => 1]];
        });
    }

    public function test_unknown_carrier_falls_back_to_other_and_errors_are_readable(): void
    {
        Http::fake([
            'lwa.test/token' => Http::response(['access_token' => 'tok']),
            'sp.test/orders/v0/orders/702-2/orderItems' => Http::response(['payload' => ['OrderItems' => [['OrderItemId' => 'it-9', 'QuantityOrdered' => 2]]]]),
            'sp.test/orders/v0/orders/702-2/shipmentConfirmation' => Http::response(['errors' => [['code' => 'InvalidInput', 'message' => 'Order already shipped']]], 400),
        ]);

        try {
            AmazonClient::fromConfig()->confirmShipment('702-2', 'X1', 'Transportadora Z');
            $this->fail('deveria lançar');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Order already shipped', $e->getMessage());
        }

        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/shipmentConfirmation')
            && $req->data()['packageDetail']['carrierCode'] === 'Other'
            && $req->data()['packageDetail']['carrierName'] === 'Transportadora Z'
            && $req->data()['packageDetail']['orderItems'][0]['quantity'] === 2);
    }

    public function test_notify_job_confirms_on_amazon_using_the_amazon_order_number(): void
    {
        config(['hub.bling.client_id' => null]); // sem Bling: só a Amazon
        $customer = Customer::create(['name' => 'Guilherme']);
        $order = Order::create(['channel_id' => Channel::bySlug(Channel::AMAZON)->id, 'external_id' => '26884098698', 'external_number' => '702-8230738-5649812',
            'customer_id' => $customer->id, 'status' => 'label_generated', 'payment_status' => 'paid', 'requires_shipping' => true, 'placed_at' => now()]);
        $shipment = Shipment::create(['order_id' => $order->id, 'status' => Shipment::LABEL_GENERATED, 'carrier' => 'Correios', 'service' => 'PAC', 'tracking_code' => 'AP502354720BR', 'melhor_envio_id' => 'me-3']);

        Http::fake([
            'lwa.test/token' => Http::response(['access_token' => 'tok']),
            'sp.test/orders/v0/orders/702-8230738-5649812/orderItems' => Http::response(['payload' => ['OrderItems' => [['OrderItemId' => 'i1', 'QuantityOrdered' => 1]]]]),
            'sp.test/orders/v0/orders/702-8230738-5649812/shipmentConfirmation' => Http::response('', 204),
        ]);

        (new NotifyChannelShipped($shipment->id))->handle();

        $this->assertTrue($shipment->fresh()->channel_notified);
        $this->assertSame(1, SyncLog::where('action', 'tracking.sent')->count());
        Http::assertSent(fn ($req) => str_contains($req->url(), '/orders/702-8230738-5649812/shipmentConfirmation'));
    }
}
