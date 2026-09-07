<?php

namespace App\Integrations\PagarMe;

use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;

/**
 * Traduz um pedido (or_...) do Core v5 para o formato interno.
 *
 * No Pagar.me o pedido nasce do checkout e carrega cliente, endereço de
 * entrega, itens e cobranças (charges). O status de pagamento é o da
 * cobrança; o do pedido resume as cobranças.
 */
class OrderMapper
{
    public static function status(array $o, bool $requiresShipping): OrderStatus
    {
        return match ($o['status'] ?? '') {
            'paid' => $requiresShipping ? OrderStatus::Paid : OrderStatus::Fulfilled,
            'pending' => OrderStatus::AwaitingPayment,
            'canceled', 'failed' => OrderStatus::Cancelled,
            default => OrderStatus::New,
        };
    }

    public static function paymentStatus(array $o): string
    {
        return match ($o['status'] ?? '') {
            'paid' => 'paid',
            'canceled' => 'refunded',
            'failed' => 'failed',
            default => 'pending',
        };
    }

    public static function paymentMethod(array $o): ?string
    {
        $charge = collect($o['charges'] ?? [])->last();

        return $charge['payment_method'] ?? null; // pix | credit_card | boleto
    }

    /** Há endereço de entrega → produto físico. Checkout só digital não manda shipping. */
    public static function requiresShipping(array $o): bool
    {
        return ! empty($o['shipping']['address']['zip_code']);
    }

    public static function customer(array $o): array
    {
        $c = $o['customer'] ?? [];
        $phone = $c['phones']['mobile_phone'] ?? $c['phones']['home_phone'] ?? null;

        return [
            'name' => $c['name'] ?? 'Sem nome',
            'email' => $c['email'] ?? null,
            'phone' => $phone ? ($phone['area_code'] ?? '') . ($phone['number'] ?? '') : null,
            'document' => $c['document'] ?? null,
        ];
    }

    public static function order(array $o): array
    {
        $a = $o['shipping']['address'] ?? [];
        $requiresShipping = self::requiresShipping($o);
        $charge = collect($o['charges'] ?? [])->last();

        // line_1 vem como "número, rua, bairro" no padrão do Pagar.me.
        [$number, $street, $district] = array_pad(array_map('trim', explode(',', (string) ($a['line_1'] ?? ''), 3)), 3, null);

        return [
            'external_id' => (string) $o['id'],
            'external_number' => (string) ($o['code'] ?? $o['id']),
            'status' => self::status($o, $requiresShipping),
            'payment_status' => self::paymentStatus($o),
            'payment_method' => self::paymentMethod($o),
            'channel_status' => $o['status'] ?? null,
            'subtotal_cents' => (int) ($o['amount'] ?? 0) - (int) ($o['shipping']['amount'] ?? 0),
            'shipping_cents' => (int) ($o['shipping']['amount'] ?? 0),
            'discount_cents' => 0,
            'total_cents' => (int) ($o['amount'] ?? 0),
            'currency' => $o['currency'] ?? 'BRL',
            'ship_name' => $o['shipping']['recipient_name'] ?? ($o['customer']['name'] ?? null),
            'ship_street' => $street,
            'ship_number' => $number,
            'ship_complement' => $a['line_2'] ?? null,
            'ship_district' => $district,
            'ship_city' => $a['city'] ?? null,
            'ship_state' => $a['state'] ?? null,
            'ship_zip' => isset($a['zip_code']) ? preg_replace('/\D/', '', $a['zip_code']) : null,
            'requires_shipping' => $requiresShipping,
            'placed_at' => isset($o['created_at']) ? CarbonImmutable::parse($o['created_at']) : null,
            'paid_at' => isset($charge['paid_at']) ? CarbonImmutable::parse($charge['paid_at']) : null,
            'raw' => $o,
        ];
    }

    /** @return array<int, array> */
    public static function items(array $o): array
    {
        return collect($o['items'] ?? [])->map(fn ($it) => [
            'name' => $it['description'] ?? 'Item',
            'external_sku' => $it['code'] ?? null,
            'external_product_id' => (string) ($it['code'] ?? ''),
            'quantity' => (int) ($it['quantity'] ?? 1),
            'unit_cents' => (int) ($it['amount'] ?? 0),
            'total_cents' => (int) ($it['amount'] ?? 0) * (int) ($it['quantity'] ?? 1),
        ])->all();
    }
}
