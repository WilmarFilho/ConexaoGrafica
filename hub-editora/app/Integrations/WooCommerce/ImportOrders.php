<?php

namespace App\Integrations\WooCommerce;

use App\Models\Channel;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductChannelRef;
use App\Models\SyncLog;
use Illuminate\Support\Facades\DB;

/**
 * Importa/atualiza pedidos do WooCommerce no hub.
 *
 * Regras que vieram da operação real da Pubcon:
 * - O plugin multi-vendor (MVX) cria pedidos-filho por vendedor. Só o pedido
 *   pai (parent_id = 0) entra; senão a fila conta em dobro.
 * - Idempotente por (canal, external_id): webhook repetido ou varredura
 *   sobreposta nunca duplica.
 * - Status é sempre recalculado a partir do que o canal manda, exceto quando o
 *   hub já avançou o pedido na expedição (não regredimos "etiqueta gerada"
 *   para "pago" só porque o Woo ainda diz "processing").
 */
class ImportOrders
{
    public function __construct(
        private readonly WooCommerceClient $client,
        private readonly Channel $channel,
    ) {}

    public static function make(): self
    {
        return new self(WooCommerceClient::fromConfig(), Channel::bySlug(Channel::WOOCOMMERCE));
    }

    /** Varredura periódica: tudo que mudou nos últimos N dias. */
    public function sinceLookback(): int
    {
        $since = now()->subDays((int) config('hub.woocommerce.lookback_days', 3));
        $count = 0;

        try {
            foreach ($this->client->ordersModifiedSince($since) as $payload) {
                if ($this->upsert($payload)) {
                    $count++;
                }
            }

            $this->channel->update([
                'last_sync_at' => now(),
                'last_sync_status' => 'ok',
                'last_sync_message' => "{$count} pedido(s) processado(s)",
            ]);
        } catch (\Throwable $e) {
            $this->channel->update([
                'last_sync_at' => now(),
                'last_sync_status' => 'error',
                'last_sync_message' => $e->getMessage(),
            ]);
            SyncLog::record($this->channel, 'in', 'orders.sync', null, $e->getMessage(), [], 'error');
            throw $e;
        }

        return $count;
    }

    /** Um pedido específico (usado pelo webhook). */
    public function one(int $wooId): ?Order
    {
        $payload = $this->client->order($wooId);

        return $this->upsert($payload);
    }

    /** Grava o pedido; devolve null quando ele foi ignorado (pedido-filho). */
    public function upsert(array $payload): ?Order
    {
        if ((int) ($payload['parent_id'] ?? 0) > 0) {
            return null; // pedido-filho de vendor: o pai já representa a venda
        }

        return DB::transaction(function () use ($payload) {
            $customer = Customer::findOrCreateFrom(OrderMapper::customer($payload));
            $data = OrderMapper::order($payload);

            $order = Order::firstOrNew([
                'channel_id' => $this->channel->id,
                'external_id' => $data['external_id'],
            ]);

            $isNew = ! $order->exists;

            // Não regride um pedido que o hub já levou adiante na expedição.
            if (! $isNew && $order->status->value !== $data['status']->value && $this->hubIsAhead($order)) {
                unset($data['status']);
            }

            $order->fill([...$data, 'customer_id' => $customer->id])->save();

            $order->items()->delete();

            foreach (OrderMapper::items($payload) as $item) {
                $productId = ProductChannelRef::query()
                    ->where('channel_id', $this->channel->id)
                    ->where('external_id', $item['external_product_id'])
                    ->value('product_id');

                unset($item['external_product_id']);
                $order->items()->create([...$item, 'product_id' => $productId]);
            }

            SyncLog::record(
                $this->channel,
                'in',
                $isNew ? 'order.imported' : 'order.updated',
                $order,
                "#{$order->external_number} · {$order->status->label()}",
            );

            return $order;
        });
    }

    private function hubIsAhead(Order $order): bool
    {
        return in_array($order->status->value, [
            'picking', 'checked', 'label_generated', 'ready_to_ship', 'shipped', 'delivered',
        ], true);
    }
}
