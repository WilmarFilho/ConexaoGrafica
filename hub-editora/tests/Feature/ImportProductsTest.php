<?php

namespace Tests\Feature;

use App\Integrations\WooCommerce\ImportProducts;
use App\Integrations\WooCommerce\OrderMapper;
use App\Models\Channel;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductChannelRef;
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

    public function test_a_book_with_formats_is_one_product_and_merges_old_per_format_products(): void
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
                    'weight' => '0.9', 'dimensions' => ['length' => '', 'width' => '23', 'height' => '32'],
                    'meta_data' => [['key' => '_conexao_isbn13', 'value' => '978-65-975654-7-4']]],
            ], 200, ['X-WP-TotalPages' => '1']),
        ]);

        $woo = Channel::bySlug(Channel::WOOCOMMERCE);

        // como estava antes: um produto do hub por formato
        $impresso = Product::create(['name' => 'Neuro-oftalmologia — Impresso', 'sku' => 'WOO-21', 'physical' => true, 'active' => true, 'depth_cm' => 3]);
        $ebook = Product::create(['name' => 'Neuro-oftalmologia — E-book', 'sku' => 'WOO-22', 'physical' => false, 'active' => true]);
        ProductChannelRef::create(['channel_id' => $woo->id, 'product_id' => $impresso->id, 'external_id' => '21']);
        ProductChannelRef::create(['channel_id' => $woo->id, 'product_id' => $ebook->id, 'external_id' => '22']);

        $customer = Customer::create(['name' => 'Cliente']);
        $order = Order::create([
            'channel_id' => $woo->id, 'external_id' => '900', 'customer_id' => $customer->id,
            'status' => 'paid', 'payment_status' => 'paid', 'requires_shipping' => true, 'placed_at' => now(),
        ]);
        $semProduto = $order->items()->create(['name' => 'Neuro-oftalmologia - Impresso', 'external_sku' => '21', 'quantity' => 1, 'unit_cents' => 12000, 'total_cents' => 12000]);
        $doEbook = $order->items()->create(['name' => 'Neuro-oftalmologia - E-book', 'external_sku' => '22', 'product_id' => $ebook->id, 'quantity' => 1, 'unit_cents' => 6000, 'total_cents' => 6000]);

        $r = ImportProducts::make()->all();

        $this->assertSame(['products' => 1, 'relinked' => 1], $r);
        $this->assertSame(1, Product::count());

        $livro = $impresso->fresh();
        $this->assertSame('Neuro-oftalmologia', $livro->name);
        $this->assertSame('WOO-21', $livro->sku);
        $this->assertSame(['fisico', 'ebook'], $livro->formats); // o combo sem preço não está à venda
        $this->assertTrue($livro->physical);
        $this->assertSame(900, $livro->weight_grams);
        $this->assertSame([23, 32, 3], [$livro->width_cm, $livro->height_cm, $livro->depth_cm]);
        $this->assertSame('9786597565474', $livro->isbn);

        $this->assertSame(
            ['20' => null, '21' => 'fisico', '22' => 'ebook', '23' => 'fisico_ebook'],
            ProductChannelRef::where('product_id', $livro->id)->orderBy('external_id')->pluck('format', 'external_id')->all(),
        );

        $this->assertSame([$livro->id, 'fisico'], [$semProduto->fresh()->product_id, $semProduto->fresh()->format]);
        $this->assertSame([$livro->id, 'ebook'], [$doEbook->fresh()->product_id, $doEbook->fresh()->format]);

        // segunda rodada: nada se multiplica
        ImportProducts::make()->all();
        $this->assertSame(1, Product::count());
        $this->assertSame(4, ProductChannelRef::count());

        // livro que saiu da vitrine (rascunho na loja): o vínculo antigo não tem
        // formato, mas o produto só tem um, e o item herda esse
        $rascunho = Product::create(['name' => 'Tratado', 'sku' => 'WOO-99', 'formats' => ['ebook'], 'active' => true]);
        ProductChannelRef::create(['channel_id' => $woo->id, 'product_id' => $rascunho->id, 'external_id' => '99']);
        $antigo = $order->items()->create(['name' => 'Ebook Tratado', 'external_sku' => '99', 'product_id' => $rascunho->id, 'quantity' => 1, 'unit_cents' => 100, 'total_cents' => 100]);

        ImportProducts::make()->all();
        $this->assertSame('ebook', $antigo->fresh()->format);
    }

    public function test_formats_decide_if_the_product_is_shipped(): void
    {
        $p = Product::create(['name' => 'Livro', 'sku' => 'L-1', 'formats' => ['ebook'], 'active' => true]);
        $this->assertFalse($p->physical);

        $p->update(['formats' => ['fisico_ebook', 'ebook', 'ebook']]);
        $this->assertTrue($p->fresh()->physical);
        $this->assertSame(['ebook', 'fisico_ebook'], $p->fresh()->formats);

        $this->assertSame('Tratado de Doenças Raras', Product::cleanTitle('Ebook Tratado de Doenças Raras'));
        $this->assertSame('1º Manual de Condutas', Product::cleanTitle('E-Book 1º Manual de Condutas'));
        $this->assertSame('A História do Cremego', Product::cleanTitle('A História do Cremego - Impresso'));
        $this->assertSame('Neuro-oftalmologia', Product::cleanTitle('Neuro-oftalmologia — Impresso + E-book'));
        $this->assertSame('Ebookeria', Product::cleanTitle('Ebookeria'));

        $this->assertSame('fisico_ebook', Product::formatFromLabel('Impresso + E-book'));
        $this->assertSame('fisico', Product::formatFromLabel('Físico'));
        $this->assertSame('ebook', Product::formatFromLabel('E-book'));
        $this->assertSame('ebook', Product::formatFromLabel(null, true));
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
