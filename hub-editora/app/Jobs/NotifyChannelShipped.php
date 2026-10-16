<?php

namespace App\Jobs;

use App\Integrations\Amazon\AmazonClient;
use App\Models\Order;
use App\Integrations\WooCommerce\WooCommerceClient;
use App\Models\Channel;
use App\Models\Shipment;
use App\Models\SyncLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Devolve o rastreio ao canal de origem do pedido (WooCommerce: nota ao
 * cliente + status "concluído"; Amazon: "Confirmar envio" pela SP-API).
 * Pagar.me (landing) não tem para onde escrever.
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

        // Amazon só pela SP-API; sem credencial, adia (o próximo envio tenta de novo).
        if ($channel?->slug === Channel::AMAZON && ! AmazonClient::isConfigured()) {
            SyncLog::record($channel, 'out', 'tracking.failed', $order, 'SP-API da Amazon não configurada: confirmação de envio adiada', [], 'warning');

            return;
        }

        try {
            $sent = match ($channel?->slug) {
                Channel::WOOCOMMERCE => $this->woo($order->external_id, $shipment),
                Channel::AMAZON => $this->amazon($order, $shipment),
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

    /** Amazon: "Confirmar envio" com rastreio na SP-API, pelo número do pedido na Amazon. */
    private function amazon(Order $order, Shipment $shipment): bool
    {
        AmazonClient::fromConfig()->confirmShipment(
            $order->external_number,
            $shipment->tracking_code,
            $shipment->carrier ?: 'Correios',
            $shipment->service,
            $shipment->label_generated_at ?? now(),
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
