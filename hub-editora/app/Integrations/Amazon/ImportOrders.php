<?php

namespace App\Integrations\Amazon;

use App\Models\Channel;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductChannelRef;
use App\Models\SyncLog;
use App\Support\IntegrationAlerts;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos da Amazon direto da SP-API (única fonte de dados da Amazon no hub).
 * Varre os pedidos alterados nos últimos dias; os itens só são buscados quando
 * o pedido é novo ou mudou desde a última leitura.
 *
 * Pedidos que entraram antes por outra ponte guardam o número da Amazon em
 * external_number: são reaproveitados, não duplicados.
 */
class ImportOrders
{
    public function __construct(
        private readonly AmazonClient $client,
        private readonly Channel $channel,
    ) {}

    public static function make(): self
    {
        return new self(AmazonClient::fromConfig(), Channel::bySlug(Channel::AMAZON));
    }

    public function sinceLookback(int $days = 3): int
    {
        $count = 0;

        try {
            foreach ($this->client->ordersUpdatedSince(now()->subDays($days)) as $amazonOrder) {
                if ($this->upsert($amazonOrder)) {
                    $count++;
                }
            }

            $this->channel->update(['last_sync_at' => now(), 'last_sync_status' => 'ok', 'last_sync_message' => "{$count} pedido(s) processado(s)"]);
            IntegrationAlerts::recovered(Channel::AMAZON);
        } catch (\Throwable $e) {
            $this->channel->update(['last_sync_at' => now(), 'last_sync_status' => 'error', 'last_sync_message' => mb_substr($e->getMessage(), 0, 250)]);
            SyncLog::record($this->channel, SyncLog::IN, 'orders.sync', null, $e->getMessage(), [], 'error');
            IntegrationAlerts::down(Channel::AMAZON, $e->getMessage());
            throw $e;
        }

        return $count;
    }

    public function upsert(array $payload): ?Order
    {
        $amazonId = (string) ($payload['AmazonOrderId'] ?? '');

        if ($amazonId === '') {
            return null;
        }

        $order = Order::query()
            ->where('channel_id', $this->channel->id)
            ->where(fn ($q) => $q->where('external_id', $amazonId)->orWhere('external_number', $amazonId))
            ->first() ?? new Order(['channel_id' => $this->channel->id]);

        // Nada mudou na Amazon desde a última leitura: não gasta chamada de itens.
        $lastUpdate = $payload['LastUpdateDate'] ?? null;
        if ($order->exists && $lastUpdate && ($order->raw['LastUpdateDate'] ?? null) === $lastUpdate) {
            return null;
        }

        $items = OrderMapper::items($this->client->orderItems($amazonId));
        usleep(500_000); // getOrderItems: ritmo baixo da SP-API

        return DB::transaction(function () use ($order, $payload, $items) {
            $data = OrderMapper::order($payload);
            $customer = filled($payload['BuyerInfo'] ?? null) || filled($payload['ShippingAddress'] ?? null)
                ? Customer::findOrCreateFrom(OrderMapper::customer($payload))
                : null;

            $data['subtotal_cents'] = array_sum(array_column($items, 'total_cents'));
            $data['shipping_cents'] = array_sum(array_column($items, 'shipping_cents'));
            $data['discount_cents'] = array_sum(array_column($items, 'discount_cents'));

            $isNew = ! $order->exists;
            $from = $isNew ? null : $order->status;

            if (! $isNew && $order->status->value !== $data['status']->value && ($order->status_manual || $this->hubIsAhead($order, $data))) {
                unset($data['status']);
            }

            // Pedido antigo, já anonimizado pela retenção: não traz os dados pessoais de volta.
            if ($order->pii_purged_at) {
                foreach (['ship_name', 'ship_street', 'ship_number', 'ship_complement', 'ship_district', 'ship_zip'] as $campo) {
                    unset($data[$campo]);
                }
                $customer = null;
            }

            $order->fill([...$data, 'customer_id' => $customer?->id ?? $order->customer_id])->save();
            $changed = $from && $from !== $order->status;

            $order->items()->delete();
            foreach ($items as $item) {
                $productId = $item['external_product_id'] !== ''
                    ? ProductChannelRef::query()->where('channel_id', $this->channel->id)->where('external_id', $item['external_product_id'])->value('product_id')
                    : null;
                unset($item['external_product_id'], $item['shipping_cents'], $item['discount_cents']);
                $order->items()->create([...$item, 'product_id' => $productId]);
            }

            if ($isNew || $changed) {
                SyncLog::record($this->channel, SyncLog::IN, $isNew ? 'order.imported' : 'order.updated', $order,
                    $isNew ? "{$order->external_number} · {$order->status->label()}" : "{$order->external_number} · {$from->label()} → {$order->status->label()}",
                    ['from' => $from?->value, 'to' => $order->status->value]);
            }

            return $order;
        });
    }

    /** O hub já passou desse ponto (separação, etiqueta, envio): a Amazon atrasada não volta a etapa. */
    private function hubIsAhead(Order $order, array $data): bool
    {
        if (($data['status']->value ?? null) === 'cancelled') {
            return false; // cancelamento na Amazon sempre vale
        }

        return in_array($order->status->value, ['picking', 'checked', 'label_generated', 'ready_to_ship', 'shipped', 'delivered'], true);
    }
}
