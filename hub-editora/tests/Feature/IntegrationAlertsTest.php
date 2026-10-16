<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\SyncLog;
use App\Models\User;
use App\Notifications\IntegrationDown;
use App\Support\IntegrationAlerts;
use Database\Seeders\ChannelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        IntegrationAlerts::down(Channel::AMAZON, 'invalid_grant', immediate: true);
        Notification::assertCount(4);
    }

    public function test_amazon_without_sp_api_credentials_shows_as_pending(): void
    {
        config(['hub.amazon.client_id' => null, 'hub.amazon.client_secret' => null, 'hub.amazon.refresh_token' => null]);
        $this->assertContains('Amazon', array_column(IntegrationAlerts::openIssues(), 'channel'));

        config(['hub.amazon.client_id' => 'cid', 'hub.amazon.client_secret' => 'sec', 'hub.amazon.refresh_token' => 'ref']);
        $this->assertNotContains('Amazon', array_column(IntegrationAlerts::openIssues(), 'channel'));
    }
}
