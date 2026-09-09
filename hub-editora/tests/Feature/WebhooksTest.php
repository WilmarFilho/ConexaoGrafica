<?php

namespace Tests\Feature;

use App\Jobs\ImportChannelOrder;
use Database\Seeders\ChannelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhooksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChannelSeeder::class);
        Queue::fake();
        config([
            'hub.woocommerce.webhook_secret' => 'segredo-woo',
            'hub.pagarme.webhook_secret' => 'segredo-pagarme',
        ]);
    }

    private function wooSign(string $body, string $secret = 'segredo-woo'): string
    {
        return base64_encode(hash_hmac('sha256', $body, $secret, true));
    }

    public function test_woocommerce_webhook_queues_import_when_signature_is_valid(): void
    {
        $body = json_encode(['id' => 48213, 'parent_id' => 0, 'status' => 'processing']);

        $this->call('POST', '/webhooks/woocommerce', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WC_WEBHOOK_SIGNATURE' => $this->wooSign($body),
            'HTTP_X_WC_WEBHOOK_TOPIC' => 'order.updated',
        ], $body)->assertStatus(202)->assertJson(['queued' => 48213]);

        Queue::assertPushed(ImportChannelOrder::class, fn ($job) => $job->channel === 'woocommerce'
            && $job->externalId === '48213'
            && $job->event === 'order.updated');
    }

    public function test_woocommerce_webhook_rejects_bad_signature(): void
    {
        $body = json_encode(['id' => 1]);

        $this->call('POST', '/webhooks/woocommerce', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WC_WEBHOOK_SIGNATURE' => $this->wooSign($body, 'outro'),
        ], $body)->assertStatus(401);

        $this->postJson('/webhooks/woocommerce', ['id' => 1])->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_woocommerce_ignores_child_orders_and_answers_ping(): void
    {
        $child = json_encode(['id' => 9, 'parent_id' => 8]);
        $this->call('POST', '/webhooks/woocommerce', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WC_WEBHOOK_SIGNATURE' => $this->wooSign($child),
        ], $child)->assertOk()->assertJson(['ignored' => 'pedido-filho']);

        // O ping do Woo vem sem assinatura.
        $ping = http_build_query(['webhook_id' => 12]);
        $this->call('POST', '/webhooks/woocommerce', [], [], [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ], $ping)->assertOk()->assertJson(['ping' => true]);

        Queue::assertNothingPushed();
    }

    public function test_woocommerce_refuses_when_secret_missing(): void
    {
        config(['hub.woocommerce.webhook_secret' => null]);

        $this->postJson('/webhooks/woocommerce', ['id' => 1])->assertStatus(503);
    }

    public function test_pagarme_webhook_with_basic_auth_queues_order_events(): void
    {
        $this->withBasicAuth('hub', 'segredo-pagarme')
            ->postJson('/webhooks/pagarme', ['type' => 'order.paid', 'data' => ['id' => 'or_ABC123']])
            ->assertStatus(202)
            ->assertJson(['queued' => 'or_ABC123']);

        // charge.* traz o pedido aninhado.
        $this->postJson('/webhooks/pagarme?token=segredo-pagarme', [
            'type' => 'charge.paid',
            'data' => ['id' => 'ch_1', 'order' => ['id' => 'or_XYZ']],
        ])->assertStatus(202)->assertJson(['queued' => 'or_XYZ']);

        Queue::assertPushed(ImportChannelOrder::class, 2);
        Queue::assertPushed(ImportChannelOrder::class, fn ($j) => $j->channel === 'pagarme' && $j->externalId === 'or_XYZ' && $j->event === 'charge.paid');
    }

    public function test_pagarme_webhook_rejects_without_secret_and_ignores_other_events(): void
    {
        $this->postJson('/webhooks/pagarme', ['type' => 'order.paid', 'data' => ['id' => 'or_1']])->assertStatus(401);
        $this->withBasicAuth('hub', 'errado')->postJson('/webhooks/pagarme', ['type' => 'order.paid', 'data' => ['id' => 'or_1']])->assertStatus(401);

        $this->withBasicAuth('hub', 'segredo-pagarme')
            ->postJson('/webhooks/pagarme', ['type' => 'customer.created', 'data' => ['id' => 'cus_1']])
            ->assertOk()->assertJson(['ignored' => 'customer.created']);

        Queue::assertNothingPushed();
    }
}
