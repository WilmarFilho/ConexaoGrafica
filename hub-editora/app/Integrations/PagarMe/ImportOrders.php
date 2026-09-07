<?php

namespace App\Integrations\PagarMe;

use App\Models\Channel;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductChannelRef;
use App\Models\SyncLog;
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
                $this->upsert($payload);
                $count++;
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

    public function one(string $orderId): Order
    {
        return $this->upsert($this->client->order($orderId));
    }

    public function upsert(array $payload): Order
    {
        return DB::transaction(function () use ($payload) {
            $customer = Customer::findOrCreateFrom(OrderMapper::customer($payload));
            $data = OrderMapper::order($payload);

            $order = Order::firstOrNew([
                'channel_id' => $this->channel->id,
                'external_id' => $data['external_id'],
            ]);

            $isNew = ! $order->exists;

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
                "{$order->external_number} · {$order->status->label()}",
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
