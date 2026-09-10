<?php

namespace App\Filament\Widgets;

use App\Integrations\Bling\BlingClient;
use App\Integrations\MelhorEnvio\MelhorEnvioClient;
use App\Models\Channel;
use App\Models\Order;
use App\Models\SyncLog;
use Filament\Widgets\Widget;

/**
 * Situação de cada integração: configurada, última sincronização, erros
 * desde o último sucesso. Bling e Amazon aparecem como uma linha só, porque
 * hoje a Amazon chega exclusivamente pelo Bling.
 */
class ChannelsHealth extends Widget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 4;

    protected string $view = 'filament.widgets.channels-health';

    protected ?string $pollingInterval = '120s';

    public function getRows(): array
    {
        $channels = Channel::query()->get()->keyBy('slug');
        $woo = $channels[Channel::WOOCOMMERCE] ?? null;
        $pm = $channels[Channel::PAGARME] ?? null;
        $amz = $channels[Channel::AMAZON] ?? null;
        $bling = $channels[Channel::BLING] ?? null;
        $me = $channels[Channel::MELHOR_ENVIO] ?? null;

        return array_values(array_filter([
            $this->row('WooCommerce (Pubcon)', [$woo],
                filled(config('hub.woocommerce.url')) && filled(config('hub.woocommerce.key')), orders: [$woo]),

            $this->row('Pagar.me (landing pages)', [$pm],
                filled(config('hub.pagarme.secret_key')), orders: [$pm]),

            $this->row('Bling / Amazon', [$bling, $amz],
                BlingClient::isConfigured() && BlingClient::isConnected(), orders: [$amz],
                offLabel: BlingClient::isConfigured() ? 'Falta autorizar' : 'Sem credenciais'),

            $this->row('Melhor Envio', [$me], MelhorEnvioClient::isConfigured()),
        ]));
    }

    /**
     * @param  array<int, ?Channel>  $channels  canais que compõem a linha
     * @param  array<int, ?Channel>  $orders    canais cujos pedidos contam nos 30 dias (null = não mostra)
     */
    private function row(string $name, array $channels, bool $configured, ?array $orders = null, string $offLabel = 'Sem credenciais'): ?array
    {
        $channels = array_values(array_filter($channels));
        if (! $channels) {
            return null;
        }
        $ids = array_map(fn (Channel $c) => $c->id, $channels);

        // Último sucesso entre os canais da linha; erros só contam depois dele
        // (um erro já resolvido não fica assombrando o painel).
        $lastOk = collect($channels)->filter(fn ($c) => $c->last_sync_status === 'ok')->max('last_sync_at');
        $lastSync = collect($channels)->max('last_sync_at');
        $errors = SyncLog::query()
            ->whereIn('channel_id', $ids)
            ->where('level', 'error')
            ->where('created_at', '>=', $lastOk ?? now()->subDay())
            ->count();

        $syncFailed = collect($channels)->contains(fn ($c) => $c->last_sync_status === 'error' && (! $lastOk || $c->last_sync_at > $lastOk));

        $orders30 = null;
        if ($orders) {
            $orderIds = array_map(fn (Channel $c) => $c->id, array_values(array_filter($orders)));
            $orders30 = $orderIds ? Order::query()->whereIn('channel_id', $orderIds)->where('placed_at', '>=', now()->subDays(30))->count() : 0;
        }

        return [
            'name' => $name,
            'state' => ! $configured ? 'off' : (($errors || $syncFailed) ? 'error' : 'ok'),
            'label' => ! $configured ? $offLabel : (($errors || $syncFailed) ? 'Com erro' : 'Ativo'),
            'sync' => $lastSync?->diffForHumans(),
            'errors' => $errors,
            'orders30' => $orders30,
        ];
    }
}
