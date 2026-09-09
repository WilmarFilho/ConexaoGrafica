<?php

namespace Tests\Feature;

use App\Integrations\PagarMe\ImportOrders;
use App\Integrations\PagarMe\PagarMeClient;
use App\Models\Channel;
use App\Models\Order;
use Database\Seeders\ChannelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagarMeImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChannelSeeder::class);
    }

    private function importer(): ImportOrders
    {
        return new ImportOrders(new PagarMeClient('sk_test', 'https://api.test'), Channel::bySlug(Channel::PAGARME));
    }

    private function payload(array $metadata, string $code = 'ABC123'): array
    {
        return [
            'id' => 'or_'.$code,
            'code' => $code,
            'amount' => 1990,
            'currency' => 'BRL',
            'status' => 'paid',
            'created_at' => '2026-09-01T10:00:00Z',
            'metadata' => $metadata,
            'customer' => ['name' => 'Cliente Teste', 'email' => 'c@t.com', 'document' => '12345678909', 'phones' => []],
            'items' => [['description' => 'E-book', 'code' => 'ebook-x', 'quantity' => 1, 'amount' => 1990]],
            'charges' => [['status' => 'paid', 'payment_method' => 'pix', 'paid_at' => '2026-09-01T10:01:00Z', 'amount' => 1990]],
        ];
    }

    public function test_landing_page_orders_are_imported(): void
    {
        $order = $this->importer()->upsert($this->payload(['store' => 'ovacuodopoder']));

        $this->assertNotNull($order);
        $this->assertSame('ovacuodopoder', $order->source_label);
        $this->assertSame(1, Order::count());
    }

    public function test_orders_created_by_the_woocommerce_module_are_skipped_and_stale_copies_removed(): void
    {
        $woo = $this->payload([
            'moduleVersion' => '3.9.0',
            'coreVersion' => '2.6.0',
            'platformVersion' => ' Wordpress/7.1 Woocommerce/11.0.0',
        ], '8250');

        // Simula uma cópia importada por versão antiga do hub.
        $stale = $this->importer()->upsert($this->payload(['store' => 'x'], '8250'));
        $this->assertSame(1, Order::count());

        $this->assertNull($this->importer()->upsert($woo));
        $this->assertSame(0, Order::count(), 'a cópia antiga do pedido da loja deve ser removida');
        $this->assertDatabaseMissing('order_items', ['order_id' => $stale->id]);
    }
}
