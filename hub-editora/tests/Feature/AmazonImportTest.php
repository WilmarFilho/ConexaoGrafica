<?php

namespace Tests\Feature;

use App\Integrations\Amazon\ImportOrders;
use App\Integrations\Amazon\OrderMapper;
use App\Models\Channel;
use App\Models\Customer;
use App\Models\Order;
use App\Models\SyncLog;
use Database\Seeders\ChannelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AmazonImportTest extends TestCase
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

    private function amazonOrder(array $over = []): array
    {
        return array_replace_recursive([
            'AmazonOrderId' => '702-8230738-5649812',
            'PurchaseDate' => '2026-10-14T13:20:00Z',
            'LastUpdateDate' => '2026-10-14T15:00:00Z',
            'OrderStatus' => 'Unshipped',
            'FulfillmentChannel' => 'MFN',
            'OrderTotal' => ['CurrencyCode' => 'BRL', 'Amount' => '312.90'],
            'ShippingAddress' => [
                'Name' => 'Maria Souza', 'AddressLine1' => 'Rua 227-A, 20', 'AddressLine2' => 'Apto 3',
                'District' => 'Setor Leste Universitário', 'City' => 'Goiânia', 'StateOrRegion' => 'Goiás',
                'PostalCode' => '74610-155', 'CountryCode' => 'BR', 'Phone' => '62999990000',
            ],
            'BuyerInfo' => ['BuyerEmail' => 'abc123@marketplace.amazon.com.br', 'BuyerName' => 'Maria Souza'],
        ], $over);
    }

    private function fakeAmazon(array $orders): void
    {
        Http::fake([
            'lwa.test/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'sp.test/tokens/2021-03-01/restrictedDataToken' => Http::response(['restrictedDataToken' => 'rdt', 'expiresIn' => 3600]),
            'sp.test/orders/v0/orders/*/orderItems' => Http::response(['payload' => ['OrderItems' => [[
                'OrderItemId' => 'i1', 'SellerSKU' => 'FUND-OF-001', 'Title' => 'Fundamentos da Oftalmologia',
                'QuantityOrdered' => 1, 'ItemPrice' => ['Amount' => '297.00'], 'ShippingPrice' => ['Amount' => '15.90'],
            ]]]]),
            'sp.test/orders/v0/orders*' => Http::response(['payload' => ['Orders' => $orders]]),
        ]);
    }

    public function test_imports_orders_from_sp_api_with_address_but_without_pii_in_raw(): void
    {
        $this->fakeAmazon([$this->amazonOrder()]);

        $this->assertSame(1, ImportOrders::make()->sinceLookback(3));

        $order = Order::where('external_id', '702-8230738-5649812')->firstOrFail();
        $this->assertSame('paid', $order->status->value);
        $this->assertSame('702-8230738-5649812', $order->external_number);
        $this->assertSame('Rua 227-A', $order->ship_street);
        $this->assertSame('20', $order->ship_number);
        $this->assertSame('Apto 3', $order->ship_complement);
        $this->assertSame('GO', $order->ship_state);
        $this->assertSame('74610155', $order->ship_zip);
        $this->assertSame(31290, $order->total_cents);
        $this->assertSame(1590, $order->shipping_cents);
        $this->assertSame('Maria Souza', $order->customer->name);
        $this->assertArrayNotHasKey('ShippingAddress', $order->raw);
        $this->assertArrayNotHasKey('BuyerInfo', $order->raw);
        $this->assertSame('Fundamentos da Oftalmologia', $order->items->first()->name);

        // Endereço e comprador pedidos com o token de dados restritos.
        Http::assertSent(fn ($req) => str_contains($req->url(), '/orders/v0/orders?') && $req->hasHeader('x-amz-access-token', 'rdt'));
        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/restrictedDataToken')
            && $req->data()['restrictedResources'][0]['dataElements'] === ['buyerInfo', 'shippingAddress']);

        // Sem dados pessoais nas mensagens de auditoria.
        $this->assertStringNotContainsString('Maria', SyncLog::where('action', 'order.imported')->first()->message);
    }

    public function test_reuses_orders_that_came_from_the_previous_bridge_and_skips_unchanged(): void
    {
        $amz = Channel::bySlug(Channel::AMAZON);
        Order::create(['channel_id' => $amz->id, 'external_id' => '26884098698', 'external_number' => '702-8230738-5649812',
            'status' => 'shipped', 'payment_status' => 'paid', 'requires_shipping' => true, 'placed_at' => now()]);

        $this->fakeAmazon([$this->amazonOrder(['OrderStatus' => 'Shipped'])]);
        ImportOrders::make()->sinceLookback(3);

        $this->assertSame(1, Order::where('channel_id', $amz->id)->count());
        $this->assertSame('702-8230738-5649812', Order::first()->external_id);

        // Segunda leitura com o mesmo LastUpdateDate não busca itens de novo.
        $itens = fn () => Http::recorded(fn ($req) => str_contains($req->url(), '/orderItems'))->count();
        $antes = $itens();
        $this->assertSame(0, ImportOrders::make()->sinceLookback(3));
        $this->assertSame($antes, $itens());
    }

    public function test_cancellation_wins_and_purged_orders_stay_anonymous(): void
    {
        $amz = Channel::bySlug(Channel::AMAZON);
        $c = Customer::create(['name' => 'Comprador Amazon (dados apagados)']);
        Order::create(['channel_id' => $amz->id, 'external_id' => '702-8230738-5649812', 'external_number' => '702-8230738-5649812', 'customer_id' => $c->id,
            'status' => 'label_generated', 'payment_status' => 'paid', 'requires_shipping' => true, 'placed_at' => now(), 'pii_purged_at' => now(), 'raw' => ['LastUpdateDate' => 'antigo']]);

        $this->fakeAmazon([$this->amazonOrder(['OrderStatus' => 'Canceled'])]);
        ImportOrders::make()->sinceLookback(3);

        $order = Order::first();
        $this->assertSame('cancelled', $order->status->value);
        $this->assertNull($order->ship_street);
        $this->assertSame('Comprador Amazon (dados apagados)', $order->customer->name);
    }

    public function test_street_number_and_state_parsing(): void
    {
        $this->assertSame(['Av. T-3', '1855'], OrderMapper::streetAndNumber('Av. T-3, 1855', null));
        $this->assertSame(['Rua das Flores', '120'], OrderMapper::streetAndNumber('Rua das Flores', '120 casa 2'));
        $this->assertSame(['Quadra 103 Norte', null], OrderMapper::streetAndNumber('Quadra 103 Norte', 'Sala 9'));
        $this->assertSame('SP', OrderMapper::uf('São Paulo'));
        $this->assertSame('TO', OrderMapper::uf('to'));
        $this->assertNull(OrderMapper::uf('Narnia'));
    }

    public function test_sync_command_uses_sp_api_and_skips_without_credentials(): void
    {
        config(['hub.amazon.client_id' => null]);
        $this->artisan('hub:sync amazon')->expectsOutputToContain('não configurado')->assertSuccessful();
    }
}
