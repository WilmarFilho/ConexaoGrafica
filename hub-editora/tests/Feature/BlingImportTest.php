<?php

namespace Tests\Feature;

use App\Integrations\Bling\BlingClient;
use App\Integrations\Bling\ImportOrders;
use App\Models\Channel;
use App\Models\Order;
use App\Models\Setting;
use Database\Seeders\ChannelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BlingImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChannelSeeder::class);
        config(['hub.bling.client_id' => 'cid', 'hub.bling.client_secret' => 'csec', 'hub.bling.base_url' => 'https://api.bling.test/Api/v3']);
        Setting::create(['key' => 'bling.access_token', 'value' => 'tok']);
        Setting::create(['key' => 'bling.refresh_token', 'value' => 'ref']);
        Setting::create(['key' => 'bling.expires_at', 'value' => (string) now()->addHours(5)->timestamp]);
        cache()->forget('hub.settings.v1');
    }

    private function detail(int $id, string $numeroLoja, int $situacao = 6): array
    {
        return ['data' => [
            'id' => $id, 'numero' => 1000 + $id, 'numeroLoja' => $numeroLoja, 'data' => '2026-09-08',
            'situacao' => ['id' => $situacao, 'valor' => $situacao],
            'contato' => ['nome' => 'Comprador Amazon', 'numeroDocumento' => '123.456.789-09', 'celular' => '(62) 99999-0000'],
            'total' => 59.9, 'totalProdutos' => 49.9,
            'transporte' => ['frete' => 10.0, 'etiqueta' => ['nome' => 'Comprador Amazon', 'endereco' => 'Rua A', 'numero' => '10', 'bairro' => 'Centro', 'municipio' => 'Goiânia', 'uf' => 'GO', 'cep' => '74000-000']],
            'itens' => [['codigo' => 'LIV-001', 'descricao' => 'Livro', 'quantidade' => 1, 'valor' => 49.9, 'produto' => ['id' => 77]]],
        ]];
    }

    public function test_imports_only_amazon_orders_from_bling(): void
    {
        Http::fake([
            'api.bling.test/Api/v3/pedidos/vendas?*' => Http::response(['data' => [
                ['id' => 1, 'numero' => 1001, 'numeroLoja' => '701-1234567-1234567'],
                ['id' => 2, 'numero' => 1002, 'numeroLoja' => '8250'], // loja Woo, já tem dono
                ['id' => 3, 'numero' => 1003, 'numeroLoja' => ''],     // venda manual
            ]]),
            'api.bling.test/Api/v3/pedidos/vendas/1' => Http::response($this->detail(1, '701-1234567-1234567')),
        ]);

        $n = ImportOrders::make()->sinceLookback(3);

        $this->assertSame(1, $n);
        $this->assertSame(1, Order::count());

        $o = Order::first();
        $this->assertSame('amazon', $o->channel->slug);
        $this->assertSame('701-1234567-1234567', $o->external_number);
        $this->assertTrue($o->requires_shipping);
        $this->assertSame('paid', $o->payment_status);
        $this->assertSame('Pago · aguardando envio', $o->status->label());
        $this->assertSame(5990, $o->total_cents);
        $this->assertSame('74000-000', $o->ship_zip);
        $this->assertSame(1, $o->items()->count());
    }

    public function test_renews_the_token_when_expired(): void
    {
        Setting::where('key', 'bling.expires_at')->update(['value' => (string) now()->subMinute()->timestamp]);
        cache()->forget('hub.settings.v1');

        Http::fake([
            BlingClient::TOKEN_URL => Http::response(['access_token' => 'novo', 'refresh_token' => 'ref2', 'expires_in' => 21600]),
            'api.bling.test/Api/v3/pedidos/vendas/5' => Http::response($this->detail(5, '702-0000000-0000000', 9)),
        ]);

        $o = ImportOrders::make()->one(5);

        $this->assertSame('Enviado', $o->status->label());
        $this->assertSame('novo', BlingClient::token('access_token'));
        Http::assertSent(fn ($req) => $req->url() === BlingClient::TOKEN_URL && $req['grant_type'] === 'refresh_token');
    }
}
