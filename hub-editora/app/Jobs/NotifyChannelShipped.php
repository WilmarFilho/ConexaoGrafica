<?php

namespace App\Jobs;

use App\Integrations\Amazon\AmazonClient;
use App\Integrations\Bling\BlingClient;
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

        // Amazon: direto pela SP-API quando configurada; senão pelo Bling; sem nenhum, adia.
        if ($channel?->slug === Channel::AMAZON && ! AmazonClient::isConfigured() && ! (BlingClient::isConfigured() && BlingClient::isConnected())) {
            SyncLog::record($channel, 'out', 'tracking.failed', $order, 'Amazon/Bling não configurados: confirmação de envio adiada', [], 'warning');

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

    /**
     * Amazon: "Confirmar envio" direto na SP-API (com rastreio) e, quando o Bling
     * está conectado, também grava o rastreio lá e marca o pedido como atendido,
     * para o Bling não ficar defasado. O número da Amazon está em external_number;
     * external_id é o id do pedido no Bling.
     */
    private function amazon(Order $order, Shipment $shipment): bool
    {
        $confirmed = false;

        if (AmazonClient::isConfigured()) {
            AmazonClient::fromConfig()->confirmShipment(
                $order->external_number,
                $shipment->tracking_code,
                $shipment->carrier ?: 'Correios',
                $shipment->service,
                $shipment->label_generated_at ?? now(),
            );
            $confirmed = true;
        }

        if (BlingClient::isConfigured() && BlingClient::isConnected()) {
            try {
                BlingClient::fromConfig()->markShipped((int) $order->external_id, $shipment->tracking_code, $shipment->service ?: 'PAC');
                $confirmed = true;
            } catch (Throwable $e) {
                if (! $confirmed) {
                    throw $e;
                }
                SyncLog::record($order->channel, 'out', 'tracking.failed', $order, 'Amazon confirmada, mas o Bling não aceitou o rastreio: '.$e->getMessage(), [], 'warning');
            }
        }

        return $confirmed;
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
