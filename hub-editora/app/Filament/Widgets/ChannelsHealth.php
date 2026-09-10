<?php

namespace App\Filament\Widgets;

use App\Integrations\Bling\BlingClient;
use App\Integrations\MelhorEnvio\MelhorEnvioClient;
use App\Models\Channel;
use App\Models\Order;
use App\Models\SyncLog;
use Filament\Widgets\Widget;

/** Situação de cada canal: configurado, última sincronização, erros recentes. */
class ChannelsHealth extends Widget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 4;

    protected string $view = 'filament.widgets.channels-health';

    protected ?string $pollingInterval = '120s';

    public function getRows(): array
    {
        $rows = [];

        foreach (Channel::query()->orderBy('id')->get() as $c) {
            $configured = match ($c->slug) {
                Channel::WOOCOMMERCE => filled(config('hub.woocommerce.url')) && filled(config('hub.woocommerce.key')),
                Channel::PAGARME => filled(config('hub.pagarme.secret_key')),
                Channel::MELHOR_ENVIO => MelhorEnvioClient::isConfigured(),
                Channel::BLING => BlingClient::isConfigured() && BlingClient::isConnected(),
                Channel::AMAZON => BlingClient::isConfigured() && BlingClient::isConnected(),
                default => false,
            };

            $errors = SyncLog::query()->where('channel_id', $c->id)->where('level', 'error')->where('created_at', '>=', now()->subDay())->count();
            $orders30 = in_array($c->slug, [Channel::WOOCOMMERCE, Channel::PAGARME, Channel::AMAZON], true)
                ? Order::query()->where('channel_id', $c->id)->where('placed_at', '>=', now()->subDays(30))->count()
                : null;

            $rows[] = [
                'name' => $c->name,
                'state' => $configured === null ? 'soon' : ($errors ? 'error' : ($configured ? 'ok' : 'off')),
                'sync' => $c->last_sync_at?->diffForHumans(),
                'errors' => $errors,
                'orders30' => $orders30,
            ];
        }

        return $rows;
    }
}
