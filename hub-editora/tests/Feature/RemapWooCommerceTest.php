<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductChannelRef;
use Database\Seeders\ChannelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemapWooCommerceTest extends TestCase
{
    use RefreshDatabase;

    public function test_swaps_ids_after_the_store_moved_and_is_safe_to_repeat(): void
    {
        $this->seed(ChannelSeeder::class);

        $woo = Channel::bySlug(Channel::WOOCOMMERCE);
        $customer = Customer::create(['name' => 'Cliente']);

        $novo = fn (string $id) => Order::create([
            'channel_id' => $woo->id, 'external_id' => $id, 'external_number' => $id, 'customer_id' => $customer->id,
            'status' => 'paid', 'payment_status' => 'paid', 'requires_shipping' => true, 'placed_at' => now(),
        ]);

        $migrado = $novo('8300');
        $semPar = $novo('8301');
        $item = $migrado->items()->create(['name' => 'Livro', 'external_sku' => '301', 'quantity' => 1, 'unit_cents' => 100, 'total_cents' => 100]);

        // o id novo de um produto (301) é o id antigo de outro: a troca não pode tropeçar
        $a = Product::create(['name' => 'Livro A', 'sku' => 'A', 'physical' => true, 'active' => true]);
        $b = Product::create(['name' => 'Livro B', 'sku' => 'B', 'physical' => true, 'active' => true]);
        ProductChannelRef::create(['channel_id' => $woo->id, 'product_id' => $a->id, 'external_id' => '296']);
        ProductChannelRef::create(['channel_id' => $woo->id, 'product_id' => $b->id, 'external_id' => '301']);

        $mapa = storage_path('framework/testing/mapa-remap.json');
        @mkdir(dirname($mapa), 0777, true);
        file_put_contents($mapa, json_encode([
            'orders' => ['8300' => '812'],
            'products' => ['296' => '301', '301' => '305'],
        ]));

        // simulação não grava
        $this->artisan('hub:remap-woocommerce', ['file' => $mapa])->assertSuccessful();
        $this->assertSame('8300', $migrado->fresh()->external_id);

        $this->artisan('hub:remap-woocommerce', ['file' => $mapa, '--apply' => true])->assertSuccessful();

        $this->assertSame('812', $migrado->fresh()->external_id);
        $this->assertSame('8300', $migrado->fresh()->external_number);
        $this->assertSame('pubcon-8301', $semPar->fresh()->external_id);
        $this->assertSame('305', $item->fresh()->external_sku);
        $this->assertSame('301', ProductChannelRef::where('product_id', $a->id)->value('external_id'));
        $this->assertSame('305', ProductChannelRef::where('product_id', $b->id)->value('external_id'));

        // segunda rodada: nada muda
        $this->artisan('hub:remap-woocommerce', ['file' => $mapa, '--apply' => true])->assertSuccessful();

        $this->assertSame('812', $migrado->fresh()->external_id);
        $this->assertSame('305', $item->fresh()->external_sku);
        $this->assertSame('301', ProductChannelRef::where('product_id', $a->id)->value('external_id'));
        $this->assertSame('305', ProductChannelRef::where('product_id', $b->id)->value('external_id'));
    }
}
