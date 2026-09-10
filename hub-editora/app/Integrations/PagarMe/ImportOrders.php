<?php

namespace App\Integrations\PagarMe;

use App\Models\Channel;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductChannelRef;
use App\Models\SyncLog;
use App\Support\IntegrationAlerts;
use Illuminate\Support\Facades\DB;

/**
 * Importa/atualiza pedidos do Pagar.me (checkouts das landing pages).
 * Mesmas garantias da importação do WooCommerce: idempotência por
 * (canal, or_id) e nunca regredir um pedido que o hub já expediu.
 */
class ImportOrders
{
    public function __construct(
        private readonly PagarMeClient $client,
        private readonly Channel $channel,
    ) {}

    public static function make(): self
    {
        return new self(PagarMeClient::fromConfig(), Channel::bySlug(Channel::PAGARME));
    }

    public function sinceLookback(int $days = 3): int
    {
        $count = 0;

        try {
            foreach ($this->client->ordersCreatedSince(now()->subDays($days)) as $payload) {
                if ($this->upsert($payload)) {
                    $count++;
                }
            }

            $this->channel->update([
                'last_sync_at' => now(),
                'last_sync_status' => 'ok',
                'last_sync_message' => "{$count} pedido(s) processado(s)",
            ]);
            IntegrationAlerts::recovered(Channel::PAGARME);
        } catch (\Throwable $e) {
            $this->channel->update([
                'last_sync_at' => now(),
                'last_sync_status' => 'error',
                'last_sync_message' => $e->getMessage(),
            ]);
            SyncLog::record($this->channel, 'in', 'orders.sync', null, $e->getMessage(), [], 'error');
            IntegrationAlerts::down(Channel::PAGARME, $e->getMessage());
            throw $e;
        }

        return $count;
    }

    public function one(string $orderId): ?Order
    {
        return $this->upsert($this->client->order($orderId));
    }

    /**
     * A loja Pubcon cobra pelo módulo do Pagar.me, então cada pedido do
     * WooCommerce também existe no Pagar.me. O dono desse pedido é o
     * WooCommerce; aqui só entram os checkouts diretos (landing pages).
     */
    public static function cameFromWooCommerce(array $payload): bool
    {
        $meta = $payload['metadata'] ?? [];
        if (! is_array($meta)) {
            return false;
        }

        return isset($meta['moduleVersion'])
            || str_contains(strtolower((string) ($meta['platformVersion'] ?? '')), 'woocommerce');
    }

    /** Grava o pedido; devolve null quando ele pertence a outro canal (loja). */
    public function upsert(array $payload): ?Order
    {
        if (self::cameFromWooCommerce($payload)) {
            // Se alguma versão anterior chegou a importar, remove: o pedido vive no canal WooCommerce.
            $stale = Order::query()
                ->where('channel_id', $this->channel->id)
                ->where('external_id', (string) $payload['id'])
                ->first();

            if ($stale) {
                $stale->items()->delete();
                $stale->shipment()->delete();
                $stale->delete();
                SyncLog::record($this->channel, 'in', 'order.removed_duplicate', null,
                    "{$payload['id']} (pedido #{$payload['code']} da loja) removido: já existe no canal WooCommerce");
            }

            return null;
        }

        return DB::transaction(function () use ($payload) {
            $customer = Customer::findOrCreateFrom(OrderMapper::customer($payload));
            $data = OrderMapper::order($payload);

            $order = Order::firstOrNew([
                'channel_id' => $this->channel->id,
                'external_id' => $data['external_id'],
            ]);

            $isNew = ! $order->exists;
            $from = $isNew ? null : $order->status;

            if (! $isNew && $order->status->value !== $data['status']->value && ($order->status_manual || $this->hubIsAhead($order))) {
                unset($data['status']);
            }

            $order->fill([...$data, 'customer_id' => $customer->id])->save();
            $changed = $from && $from !== $order->status;

            $order->items()->delete();

            foreach (OrderMapper::items($payload) as $item) {
                $productId = ProductChannelRef::query()
                    ->where('channel_id', $this->channel->id)
                    ->where('external_id', $item['external_product_id'])
                    ->value('product_id');

                unset($item['external_product_id']);
                $order->items()->create([...$item, 'product_id' => $productId]);
            }

            if ($isNew || $changed) {
                SyncLog::record(
                    $this->channel,
                    SyncLog::IN,
                    $isNew ? 'order.imported' : 'order.updated',
                    $order,
                    $isNew
                        ? "{$order->external_number} · {$order->status->label()}"
                        : "{$order->external_number} · {$from->label()} → {$order->status->label()}",
                    ['from' => $from?->value, 'to' => $order->status->value, 'channel_status' => $order->channel_status],
                );
            }

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
