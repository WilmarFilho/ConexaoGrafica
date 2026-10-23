<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Http\Middleware\VerifyAppSignature;
use App\Models\Channel;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use Database\Seeders\ChannelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API do app do leitor: pedidos de quem está logado, assinados pelo site. */
class AppOrdersApiTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'segredo-de-teste-com-mais-de-32-caracteres-0123456789';

    protected function setUp(): void
    {
        parent::setUp();
        config(['hub.app.secret' => self::SECRET]);
        $this->seed(ChannelSeeder::class);
    }

    private function pedidos(string $email, ?string $signature = null, ?int $timestamp = null)
    {
        $ts = (string) ($timestamp ?? time());

        return $this->getJson('/api/app/pedidos?email='.rawurlencode($email), [
            'X-Conexao-Timestamp' => $ts,
            'X-Conexao-Signature' => $signature ?? VerifyAppSignature::sign($ts, 'GET', '/api/app/pedidos', $email, self::SECRET),
        ]);
    }

    public function test_signature_is_required_and_checked(): void
    {
        $this->getJson('/api/app/pedidos?email=ana%40example.com')->assertStatus(401);
        $this->pedidos('ana@example.com', signature: str_repeat('0', 64))->assertStatus(401);
        $this->pedidos('ana@example.com', timestamp: time() - 600)->assertStatus(401);
        $this->pedidos('ana@example.com')->assertOk()->assertExactJson(['pedidos' => []]);

        config(['hub.app.secret' => null]);
        $this->pedidos('ana@example.com')->assertStatus(503);
    }

    public function test_orders_of_the_reader_come_mapped_for_the_app(): void
    {
        $ana = Customer::create(['name' => 'Ana', 'email' => 'ana@example.com']);
        $outro = Customer::create(['name' => 'Bia', 'email' => 'bia@example.com']);
        $woo = Channel::bySlug(Channel::WOOCOMMERCE);

        $fisico = Order::create(['channel_id' => $woo->id, 'external_id' => '10482', 'external_number' => '10482', 'customer_id' => $ana->id,
            'status' => OrderStatus::Shipped, 'payment_status' => 'paid', 'requires_shipping' => true, 'total_cents' => 8990,
            'placed_at' => now()->subDays(3), 'paid_at' => now()->subDays(3)]);
        $fisico->items()->create(['name' => 'Direito Digital', 'quantity' => 1, 'unit_cents' => 6990, 'total_cents' => 6990, 'format' => 'fisico']);
        Shipment::create(['order_id' => $fisico->id, 'status' => 'shipped', 'carrier' => 'Correios', 'service' => 'PAC', 'tracking_code' => 'AA123456789BR',
            'cost_cents' => 2500, 'label_generated_at' => now()->subDays(2), 'shipped_at' => now()->subDay(), 'problem' => 'nota interna']);

        $ebook = Order::create(['channel_id' => Channel::bySlug(Channel::PAGARME)->id, 'external_id' => 'or_abc', 'customer_id' => $ana->id,
            'status' => OrderStatus::Fulfilled, 'payment_status' => 'paid', 'requires_shipping' => false, 'total_cents' => 3990, 'placed_at' => now()->subDay()]);
        $ebook->items()->create(['name' => 'E-book Gestão', 'quantity' => 1, 'unit_cents' => 3990, 'total_cents' => 3990, 'format' => 'ebook']);

        Order::create(['channel_id' => $woo->id, 'external_id' => '999', 'customer_id' => $outro->id, 'status' => OrderStatus::Paid,
            'payment_status' => 'paid', 'requires_shipping' => true, 'total_cents' => 100, 'placed_at' => now()]);

        $res = $this->pedidos('ANA@example.com ')->assertOk()->json('pedidos');

        $this->assertCount(2, $res, 'só os pedidos do e-mail (sem diferenciar maiúsculas)');
        $this->assertSame(['hub-'.$ebook->id, 'hub-'.$fisico->id], array_column($res, 'ref'), 'mais recentes primeiro');

        [$digital, $correio] = $res;
        $this->assertSame(['concluido_digital', 'Disponível na estante', true, null], [$digital['situacao'], $digital['situacao_rotulo'], $digital['digital'], $digital['envio']]);
        $this->assertSame('Página de venda da editora', $digital['canal']);
        $this->assertSame('E-book', $digital['itens'][0]['formato']);

        $this->assertSame(['#10482', 'Loja Conexão Editora', 'a_caminho', 8990, 'pago'], [$correio['numero'], $correio['canal'], $correio['situacao'], $correio['total_centavos'], $correio['pagamento']]);
        $this->assertSame('AA123456789BR', $correio['envio']['codigo']);
        $this->assertSame('https://www.melhorrastreio.com.br/rastreio/AA123456789BR', $correio['envio']['url_rastreio']);
        $this->assertSame(['aguardando_pagamento', 'pago', 'em_separacao', 'a_caminho'], array_column($correio['eventos'], 'situacao'));

        // nada interno vaza para o leitor
        $json = json_encode($res);
        $this->assertStringNotContainsString('nota interna', $json);
        $this->assertStringNotContainsString('2500', $json);
        $this->assertStringNotContainsString('bia@example.com', $json);
    }
}
