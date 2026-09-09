<?php

namespace Tests\Feature;

use App\Integrations\WooCommerce\ImportProducts;
use App\Models\Channel;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Database\Seeders\ChannelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportProductsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChannelSeeder::class);
        config(['hub.woocommerce.url' => 'https://loja.test', 'hub.woocommerce.key' => 'ck', 'hub.woocommerce.secret' => 'cs']);
    }

    public function test_imports_catalog_converts_units_and_relinks_order_items(): void
    {
        // Duas rodadas de catálogo: a primeira com medidas, a segunda sem (a loja apagou).
        Http::fake([
            'loja.test/wp-json/wc/v3/settings/products/woocommerce_weight_unit' => Http::response(['value' => 'kg']),
            'loja.test/wp-json/wc/v3/settings/products/woocommerce_dimension_unit' => Http::response(['value' => 'cm']),
            'loja.test/wp-json/wc/v3/products*' => Http::sequence()
                ->push([
                    ['id' => 10, 'name' => 'Livro Físico', 'sku' => 'LIV-001', 'status' => 'publish', 'virtual' => false, 'downloadable' => false,
                        'weight' => '0.42', 'dimensions' => ['length' => '2.5', 'width' => '16', 'height' => '23'],
                        'manage_stock' => true, 'stock_quantity' => 7,
                        'attributes' => [['name' => 'ISBN', 'options' => ['978-85-1234-567-8']]]],
                    ['id' => 11, 'name' => 'E-book', 'sku' => '', 'status' => 'publish', 'virtual' => true, 'downloadable' => true,
                        'weight' => '', 'dimensions' => ['length' => '', 'width' => '', 'height' => '']],
                ], 200, ['X-WP-TotalPages' => '1'])
                ->push([
                    ['id' => 10, 'name' => 'Livro Físico', 'sku' => 'LIV-001', 'status' => 'publish', 'weight' => '', 'dimensions' => ['length' => '', 'width' => '', 'height' => '']],
                ], 200, ['X-WP-TotalPages' => '1']),
        ]);

        $woo = Channel::bySlug(Channel::WOOCOMMERCE);
        $customer = Customer::create(['name' => 'Cliente']);
        $order = Order::create([
            'channel_id' => $woo->id, 'external_id' => '500', 'customer_id' => $customer->id,
            'status' => 'paid', 'payment_status' => 'paid', 'requires_shipping' => true, 'placed_at' => now(),
        ]);
        $item = $order->items()->create(['name' => 'Livro Físico', 'external_sku' => 'LIV-001', 'quantity' => 1, 'unit_cents' => 1000, 'total_cents' => 1000]);

        $r = ImportProducts::make()->all();

        $this->assertSame(['products' => 2, 'relinked' => 1], $r);

        $livro = Product::where('sku', 'LIV-001')->firstOrFail();
        $this->assertTrue($livro->physical);
        $this->assertSame(420, $livro->weight_grams);
        $this->assertSame([16, 23, 3], [$livro->width_cm, $livro->height_cm, $livro->depth_cm]);
        $this->assertSame(7, $livro->stock_physical);
        $this->assertSame('9788512345678', $livro->isbn);
        $this->assertTrue($livro->hasShippingDimensions());

        $ebook = Product::where('sku', 'WOO-11')->firstOrFail();
        $this->assertFalse($ebook->physical);
        $this->assertNull($ebook->weight_grams);

        $this->assertSame($livro->id, $item->fresh()->product_id);

        // Segunda rodada: idempotente e não apaga medidas digitadas no hub.
        $livro->update(['depth_cm' => 4]);
        ImportProducts::make()->all();

        $this->assertSame(1, Product::where('sku', 'LIV-001')->count());
        $this->assertSame(4, $livro->fresh()->depth_cm);
        $this->assertSame(420, $livro->fresh()->weight_grams);
    }
}
