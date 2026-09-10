<?php

namespace App\Integrations\Bling;

use App\Models\Channel;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductChannelRef;
use App\Models\SyncLog;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos da Amazon via Bling. Só entram os pedidos cujo número de loja tem
 * o formato da Amazon; o resto que o Bling conhece (loja Woo, vendas manuais)
 * já tem dono ou não interessa à expedição.
 */
class ImportOrders
{
    public function __construct(
        private readonly BlingClient $client,
        private readonly Channel $channel,
    ) {}

    public static function make(): self
    {
        return new self(BlingClient::fromConfig(), Channel::bySlug(Channel::AMAZON));
    }

    public function sinceLookback(int $days = 3): int
    {
        $count = 0;

        try {
            foreach ($this->client->ordersModifiedSince(now()->subDays($days)) as $summary) {
                if (! OrderMapper::isAmazonNumber($summary['numeroLoja'] ?? null)) {
                    continue;
                }
                if ($this->upsert($this->client->order((int) $summary['id']))) {
                    $count++;
                }
                usleep(350000);
            }

            $this->channel->update(['last_sync_at' => now(), 'last_sync_status' => 'ok', 'last_sync_message' => "{$count} pedido(s) processado(s)"]);
        } catch (\Throwable $e) {
            $this->channel->update(['last_sync_at' => now(), 'last_sync_status' => 'error', 'last_sync_message' => $e->getMessage()]);
            SyncLog::record($this->channel, SyncLog::IN, 'orders.sync', null, $e->getMessage(), [], 'error');
            throw $e;
        }

        return $count;
    }

    public function one(int $blingId): ?Order
    {
        return $this->upsert($this->client->order($blingId));
    }

    public function upsert(array $payload): ?Order
    {
        if (empty($payload['id']) || ! OrderMapper::isAmazonNumber($payload['numeroLoja'] ?? null)) {
            return null;
        }

        return DB::transaction(function () use ($payload) {
            $customer = Customer::findOrCreateFrom(OrderMapper::customer($payload));
            $data = OrderMapper::order($payload);

            $order = Order::firstOrNew(['channel_id' => $this->channel->id, 'external_id' => $data['external_id']]);
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
                SyncLog::record($this->channel, SyncLog::IN, $isNew ? 'order.imported' : 'order.updated', $order,
                    $isNew ? "{$order->external_number} · {$order->status->label()}" : "{$order->external_number} · {$from->label()} → {$order->status->label()}",
                    ['from' => $from?->value, 'to' => $order->status->value, 'bling_id' => $payload['id']]);
            }

            return $order;
        });
    }

    private function hubIsAhead(Order $order): bool
    {
        return in_array($order->status->value, ['picking', 'checked', 'label_generated', 'ready_to_ship', 'shipped', 'delivered'], true);
    }
}
