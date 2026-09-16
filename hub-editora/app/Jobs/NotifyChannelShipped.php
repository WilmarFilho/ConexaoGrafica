<?php

namespace App\Jobs;

use App\Integrations\Bling\BlingClient;
use App\Integrations\WooCommerce\WooCommerceClient;
use App\Models\Channel;
use App\Models\Shipment;
use App\Models\SyncLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Devolve o rastreio ao canal de origem do pedido (WooCommerce: nota ao
 * cliente + status "concluído"). Pagar.me (landing) não tem para onde
 * escrever; Amazon entra na fase da SP-API.
 */
class NotifyChannelShipped implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $shipmentId) {}

    public function handle(): void
    {
        $shipment = Shipment::query()->with('order.channel')->find($this->shipmentId);

        if (! $shipment || $shipment->channel_notified || ! $shipment->tracking_code) {
            return;
        }

        $order = $shipment->order;
        $channel = $order->channel;

        // Amazon depende do Bling autorizado; sem ele, deixa para a próxima verificação de rastreio.
        if ($channel?->slug === Channel::AMAZON && ! (BlingClient::isConfigured() && BlingClient::isConnected())) {
            SyncLog::record($channel, 'out', 'tracking.failed', $order, 'Bling não conectado: confirmação de envio na Amazon adiada', [], 'warning');

            return;
        }

        try {
            $sent = match ($channel?->slug) {
                Channel::WOOCOMMERCE => $this->woo($order->external_id, $shipment),
                Channel::AMAZON => $this->amazonViaBling($order->external_id, $shipment),
                default => false, // sem destino: marca como notificado para não insistir
            };

            $shipment->update(['channel_notified' => true]);

            SyncLog::record($channel, 'out', 'tracking.sent', $order,
                $sent ? "Rastreio {$shipment->tracking_code} enviado ao canal" : 'Canal sem retorno de rastreio; marcado como notificado',
                ['tracking' => $shipment->tracking_code]);
        } catch (Throwable $e) {
            SyncLog::record($channel, 'out', 'tracking.failed', $order, $e->getMessage(), [], 'error');
            throw $e; // deixa a fila tentar de novo
        }
    }

    /**
     * Amazon: grava o rastreio no pedido do Bling e o marca como atendido; a
     * integração Bling ↔ Amazon faz o "Confirmar envio" no Seller Central.
     */
    private function amazonViaBling(string $externalId, Shipment $shipment): bool
    {
        BlingClient::fromConfig()->markShipped(
            (int) $externalId,
            $shipment->tracking_code,
            $shipment->service ?: 'PAC',
        );

        return true;
    }

    private function woo(string $externalId, Shipment $shipment): bool
    {
        WooCommerceClient::fromConfig()->markShipped(
            (int) $externalId,
            $shipment->tracking_code,
            trim(($shipment->carrier ?? '').' '.($shipment->service ?? '')),
        );

        return true;
    }
}
