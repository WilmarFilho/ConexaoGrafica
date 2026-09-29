<?php

namespace Tests\Feature;

use App\Integrations\WooCommerce\ImportProducts;
use App\Integrations\WooCommerce\OrderMapper;
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

    public function test_book_formats_become_separate_products_linked_by_variation(): void
    {
        Http::fake([
            'loja.test/wp-json/wc/v3/settings/products/*' => Http::response(['value' => 'kg']),
            'loja.test/wp-json/wc/v3/products/20/variations*' => Http::response([
                ['id' => 21, 'sku' => '', 'status' => 'publish', 'price' => '120', 'virtual' => false, 'downloadable' => false,
                    'weight' => '', 'dimensions' => ['length' => '', 'width' => '', 'height' => ''],
                    'manage_stock' => 'parent', 'attributes' => [['name' => 'Formato', 'option' => 'Impresso']]],
                ['id' => 22, 'sku' => '', 'status' => 'publish', 'price' => '60', 'virtual' => true, 'downloadable' => false,
                    'weight' => '', 'dimensions' => ['length' => '', 'width' => '', 'height' => ''],
                    'attributes' => [['name' => 'Formato', 'option' => 'E-book']]],
                ['id' => 23, 'sku' => '', 'status' => 'publish', 'price' => '', 'virtual' => false,
                    'attributes' => [['name' => 'Formato', 'option' => 'Impresso + E-book']]],
            ]),
            'loja.test/wp-json/wc/v3/products?*' => Http::response([
                ['id' => 20, 'type' => 'variable', 'name' => 'Neuro-oftalmologia', 'sku' => '', 'status' => 'publish',
                    'weight' => '0.9', 'dimensions' => ['length' => '3', 'width' => '23', 'height' => '32'],
                    'meta_data' => [['key' => '_conexao_isbn13', 'value' => '978-65-975654-7-4']]],
            ], 200, ['X-WP-TotalPages' => '1']),
        ]);

        $woo = Channel::bySlug(Channel::WOOCOMMERCE);
        $customer = Customer::create(['name' => 'Cliente']);
        $order = Order::create([
            'channel_id' => $woo->id, 'external_id' => '900', 'customer_id' => $customer->id,
            'status' => 'paid', 'payment_status' => 'paid', 'requires_shipping' => true, 'placed_at' => now(),
        ]);
        $item = $order->items()->create(['name' => 'Neuro-oftalmologia', 'external_sku' => '21', 'quantity' => 1, 'unit_cents' => 12000, 'total_cents' => 12000]);

        $r = ImportProducts::make()->all();

        $this->assertSame(['products' => 2, 'relinked' => 1], $r);

        $impresso = Product::where('sku', 'WOO-21')->firstOrFail();
        $this->assertSame('Neuro-oftalmologia — Impresso', $impresso->name);
        $this->assertTrue($impresso->physical);
        $this->assertSame(900, $impresso->weight_grams);
        $this->assertSame([23, 32, 3], [$impresso->width_cm, $impresso->height_cm, $impresso->depth_cm]);
        $this->assertSame('9786597565474', $impresso->isbn);

        $ebook = Product::where('sku', 'WOO-22')->firstOrFail();
        $this->assertFalse($ebook->physical);

        $this->assertSame(0, Product::where('sku', 'WOO-20')->count());
        $this->assertSame(0, Product::where('sku', 'WOO-23')->count());
        $this->assertSame($impresso->id, $item->fresh()->product_id);
    }

    public function test_order_items_point_to_the_variation_when_there_is_one(): void
    {
        $items = OrderMapper::items(['line_items' => [
            ['name' => 'Livro — Impresso', 'sku' => '', 'product_id' => 20, 'variation_id' => 21, 'quantity' => 2, 'total' => '240.00'],
            ['name' => 'Livro simples', 'sku' => 'LIV-9', 'product_id' => 30, 'variation_id' => 0, 'quantity' => 1, 'total' => '50.00'],
        ]]);

        $this->assertSame(['21', '21'], [$items[0]['external_product_id'], $items[0]['external_sku']]);
        $this->assertSame(['30', 'LIV-9'], [$items[1]['external_product_id'], $items[1]['external_sku']]);
    }
}
