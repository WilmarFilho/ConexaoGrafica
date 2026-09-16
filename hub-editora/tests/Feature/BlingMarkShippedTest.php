<?php

namespace Tests\Feature;

use App\Integrations\Bling\BlingClient;
use App\Jobs\NotifyChannelShipped;
use App\Models\Channel;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\SyncLog;
use Database\Seeders\ChannelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BlingMarkShippedTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://api.bling.test/Api/v3';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChannelSeeder::class);
        config(['hub.bling.client_id' => 'cid', 'hub.bling.client_secret' => 'csec', 'hub.bling.base_url' => self::BASE]);
        Setting::create(['key' => 'bling.access_token', 'value' => 'tok']);
        Setting::create(['key' => 'bling.refresh_token', 'value' => 'ref']);
        Setting::create(['key' => 'bling.expires_at', 'value' => (string) now()->addHours(5)->timestamp]);
        cache()->forget('hub.settings.v1');
    }

    public function test_marks_the_bling_order_shipped_in_two_steps_and_sets_situacao_atendido(): void
    {
        $orderJson = ['data' => ['id' => 555, 'numero' => 285, 'numeroLoja' => '701-1', 'data' => '2026-09-10', 'situacao' => ['id' => 6, 'valor' => 0],
            'contato' => ['id' => 1], 'itens' => [['produto' => ['id' => 7], 'quantidade' => 1, 'valor' => 120]],
            'transporte' => ['frete' => 13.45, 'volumes' => [], 'etiqueta' => ['cep' => '04061001']], 'notaFiscal' => ['id' => 0], 'taxas' => []]];

        Http::fake([
            self::BASE.'/logisticas/servicos' => Http::response(['data' => [
                ['id' => 14896214052, 'descricao' => 'PAC', 'aliases' => ['ME_PAC_1'], 'nomeTransportador' => 'Correios'],
                ['id' => 14896214053, 'descricao' => 'SEDEX', 'aliases' => ['ME_SEDEX_2'], 'nomeTransportador' => 'Correios'],
            ]]),
            self::BASE.'/pedidos/vendas/555/situacoes/9' => Http::response([]),
            self::BASE.'/pedidos/vendas/555' => Http::sequence()
                ->push($orderJson)                                                                                  // GET inicial
                ->push(['data' => ['id' => 555, 'tracking' => ['volumes' => [['id' => 999]], 'idsVolumes' => [999]]]]) // PUT cria volume
                ->push(['data' => ['id' => 555, 'tracking' => ['volumes' => [['id' => 999, 'codigoRastreamento' => 'AP1BR']]]]]), // PUT grava rastreio
        ]);

        $r = BlingClient::fromConfig()->markShipped(555, 'AP1BR', 'PAC');

        $this->assertSame(999, $r['volume_id']);
        $this->assertSame('ME_PAC_1', $r['service']);

        $puts = collect(Http::recorded())->filter(fn ($pair) => $pair[0]->method() === 'PUT')->map(fn ($pair) => $pair[0]->data());
        $this->assertCount(2, $puts);
        $this->assertSame([['servico' => 'ME_PAC_1', 'codigoRastreamento' => 'AP1BR']], $puts->first()['transporte']['volumes']);
        $this->assertSame(999, $puts->last()['transporte']['volumes'][0]['id']);
        $this->assertArrayNotHasKey('id', $puts->first());
        Http::assertSent(fn ($req) => $req->method() === 'PATCH' && str_ends_with($req->url(), '/pedidos/vendas/555/situacoes/9'));
    }

    public function test_notify_job_uses_bling_for_amazon_orders(): void
    {
        $customer = Customer::create(['name' => 'Comprador']);
        $order = Order::create(['channel_id' => Channel::bySlug(Channel::AMAZON)->id, 'external_id' => '555', 'external_number' => '701-1', 'customer_id' => $customer->id,
            'status' => 'label_generated', 'payment_status' => 'paid', 'requires_shipping' => true, 'placed_at' => now()]);
        $shipment = Shipment::create(['order_id' => $order->id, 'status' => Shipment::LABEL_GENERATED, 'service' => 'PAC', 'tracking_code' => 'AP1BR', 'melhor_envio_id' => 'me-1']);

        Http::fake([
            self::BASE.'/logisticas/servicos' => Http::response(['data' => [['id' => 1, 'descricao' => 'PAC', 'aliases' => ['ME_PAC_1']]]]),
            self::BASE.'/pedidos/vendas/555/situacoes/9' => Http::response([]),
            self::BASE.'/pedidos/vendas/555' => Http::sequence()
                ->push(['data' => ['id' => 555, 'situacao' => ['id' => 6], 'transporte' => ['volumes' => [['id' => 999, 'servico' => 'PAC', 'codigoRastreamento' => '']]]]])
                ->push(['data' => ['id' => 555, 'tracking' => []]]),
        ]);

        (new NotifyChannelShipped($shipment->id))->handle();

        $this->assertTrue($shipment->fresh()->channel_notified);
        $this->assertSame(1, SyncLog::where('action', 'tracking.sent')->count());
        // volume já existia: só um PUT (o do rastreio) e o PATCH da situação
        $this->assertCount(1, collect(Http::recorded())->filter(fn ($pair) => $pair[0]->method() === 'PUT'));
    }
}
