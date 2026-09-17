<?php

namespace Tests\Feature;

use App\Integrations\Bling\BlingClient;
use App\Integrations\Bling\ImportOrders;
use App\Models\Channel;
use App\Models\Setting;
use App\Models\SyncLog;
use App\Models\User;
use App\Notifications\IntegrationDown;
use App\Support\IntegrationAlerts;
use Database\Seeders\ChannelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class IntegrationAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChannelSeeder::class);
        Notification::fake();
    }

    public function test_alerts_only_after_consecutive_failures_and_once_per_window(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        // Um ou dois erros passageiros não avisam ninguém.
        IntegrationAlerts::down(Channel::PAGARME, 'HTTP 503');
        IntegrationAlerts::down(Channel::PAGARME, 'HTTP 503');
        Notification::assertNothingSent();

        // Terceira falha seguida: avisa todos, uma vez só.
        IntegrationAlerts::down(Channel::PAGARME, 'HTTP 401');
        IntegrationAlerts::down(Channel::PAGARME, 'HTTP 401 de novo');
        Notification::assertSentTo([$a, $b], IntegrationDown::class);
        Notification::assertCount(2);
        $this->assertSame(1, SyncLog::where('action', 'alert.sent')->count());
        $this->assertStringContainsString('3 falhas seguidas', SyncLog::where('action', 'alert.sent')->first()->message);

        // Voltou: zera a contagem; uma falha isolada depois não avisa.
        IntegrationAlerts::recovered(Channel::PAGARME);
        IntegrationAlerts::down(Channel::PAGARME, 'caiu outra vez');
        Notification::assertCount(2);

        // Token revogado é imediato: não volta sozinho.
        IntegrationAlerts::down(Channel::BLING, 'invalid_grant', immediate: true);
        Notification::assertCount(4);
    }

    public function test_bling_refresh_refusal_disconnects_and_alerts(): void
    {
        User::factory()->create();
        config(['hub.bling.client_id' => 'cid', 'hub.bling.client_secret' => 'csec', 'hub.bling.base_url' => 'https://api.bling.test/Api/v3']);
        Setting::create(['key' => 'bling.access_token', 'value' => 'velho']);
        Setting::create(['key' => 'bling.refresh_token', 'value' => 'ref']);
        Setting::create(['key' => 'bling.expires_at', 'value' => (string) now()->subMinute()->timestamp]);
        cache()->forget('hub.settings.v1');

        Http::fake([BlingClient::TOKEN_URL => Http::response(['error' => 'invalid_grant', 'error_description' => 'revogado'], 400)]);

        try {
            ImportOrders::make()->one(1);
            $this->fail('deveria lançar');
        } catch (\RuntimeException) {
        }

        $this->assertFalse(BlingClient::isConnected());
        Notification::assertSentTimes(IntegrationDown::class, 1);
        $this->assertContains('Bling / Amazon', array_column(IntegrationAlerts::openIssues(), 'channel'));
    }
}
