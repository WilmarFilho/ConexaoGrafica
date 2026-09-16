<?php

namespace App\Filament\Actions;

use App\Integrations\Bling\BlingClient;
use App\Integrations\Bling\ImportOrders as BlingImport;
use App\Integrations\MelhorEnvio\MelhorEnvioClient;
use App\Integrations\PagarMe\ImportOrders as PagarMeImport;
use App\Integrations\WooCommerce\ImportOrders as WooImport;
use App\Models\Channel;
use App\Models\Shipment;
use App\Models\SyncLog;
use App\Integrations\MelhorEnvio\ShipmentService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * "Sincronizar agora": roda na hora o que o agendador faria (pedidos de
 * todos os canais + rastreios/etiquetas do Melhor Envio), sem esperar o cron.
 */
class SyncNowAction
{
    public static function make(string $name = 'sincronizar'): Action
    {
        return Action::make($name)
            ->label('Sincronizar agora')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->tooltip('Busca pedidos novos em todos os canais e atualiza etiquetas e rastreios no Melhor Envio')
            ->action(function () {
                $lines = [];
                $errors = [];

                $channels = [
                    'WooCommerce' => fn () => filled(config('hub.woocommerce.key')) ? WooImport::make()->sinceLookback() : null,
                    'Pagar.me' => fn () => filled(config('hub.pagarme.secret_key')) ? PagarMeImport::make()->sinceLookback() : null,
                    'Amazon (Bling)' => fn () => BlingClient::isConfigured() && BlingClient::isConnected() ? BlingImport::make()->sinceLookback() : null,
                ];

                foreach ($channels as $label => $run) {
                    try {
                        $n = $run();
                        if ($n !== null) {
                            $lines[] = "{$label}: {$n} pedido(s)";
                        }
                    } catch (Throwable $e) {
                        $errors[] = "{$label}: ".mb_substr($e->getMessage(), 0, 120);
                    }
                }

                if (MelhorEnvioClient::isConfigured()) {
                    $service = app(ShipmentService::class);
                    $ok = 0;
                    $open = Shipment::query()->whereNotNull('melhor_envio_id')
                        ->whereIn('status', [Shipment::QUOTED, Shipment::PROBLEM, Shipment::PURCHASED, Shipment::LABEL_GENERATED, Shipment::SHIPPED])
                        ->limit(200)->get();
                    foreach ($open as $shipment) {
                        try {
                            $service->refreshTracking($shipment);
                            $ok++;
                        } catch (Throwable $e) {
                            $errors[] = 'Envio #'.$shipment->id.': '.mb_substr($e->getMessage(), 0, 100);
                        }
                    }
                    $lines[] = "Melhor Envio: {$ok} envio(s) verificados";
                }

                SyncLog::record(null, SyncLog::MANUAL, 'sync.manual', null, implode(' · ', $lines).($errors ? ' · erros: '.implode(' | ', $errors) : ''), [], $errors ? 'warning' : 'info');

                $n = Notification::make()
                    ->title($errors ? 'Sincronizado com avisos' : 'Sincronizado')
                    ->body(implode("\n", array_merge($lines, $errors)));
                $errors ? $n->warning()->persistent()->send() : $n->success()->send();
            });
    }
}
