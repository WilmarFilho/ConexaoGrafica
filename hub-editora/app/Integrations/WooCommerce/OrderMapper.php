<?php

namespace App\Integrations\WooCommerce;

use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;

/**
 * Traduz o JSON de um pedido do WooCommerce para o formato interno do hub.
 * Só transforma dados; não toca no banco.
 */
class OrderMapper
{
    /** Status do WooCommerce → status interno. */
    public static function status(string $wooStatus, bool $requiresShipping): OrderStatus
    {
        return match ($wooStatus) {
            'pending' => OrderStatus::AwaitingPayment,
            'on-hold' => OrderStatus::AwaitingPayment,   // boleto/pix aguardando
            'processing' => $requiresShipping ? OrderStatus::Paid : OrderStatus::Fulfilled,
            'completed' => $requiresShipping ? OrderStatus::Shipped : OrderStatus::Fulfilled,
            'cancelled', 'refunded', 'failed', 'trash' => OrderStatus::Cancelled,
            default => OrderStatus::New,
        };
    }

    public static function paymentStatus(string $wooStatus): string
    {
        return match ($wooStatus) {
            'processing', 'completed' => 'paid',
            'refunded' => 'refunded',
            'failed', 'cancelled' => 'failed',
            default => 'pending',
        };
    }

    /** true quando algum item é produto físico (o WooCommerce não manda isso direto; inferimos). */
    public static function requiresShipping(array $order): bool
    {
        // Se houve frete cobrado ou método de entrega, é físico.
        if (! empty($order['shipping_lines'])) {
            return true;
        }

        // Sem shipping_lines: pedido só de e-books (virtual) no padrão da Pubcon.
        return (float) ($order['shipping_total'] ?? 0) > 0;
    }

    public static function customer(array $o): array
    {
        $b = $o['billing'] ?? [];
        $meta = collect($o['meta_data'] ?? [])->pluck('value', 'key');

        return [
            'name' => trim(($b['first_name'] ?? '') . ' ' . ($b['last_name'] ?? '')) ?: ($o['customer_id'] ? "Cliente #{$o['customer_id']}" : 'Sem nome'),
            'email' => $b['email'] ?? null,
            'phone' => $b['phone'] ?? ($meta['_billing_cellphone'] ?? null),
            // Plugin "extra checkout fields for brazil" grava o CPF/CNPJ em meta.
            'document' => $meta['_billing_cpf'] ?? $meta['_billing_cnpj'] ?? null,
        ];
    }

    public static function order(array $o): array
    {
        $s = $o['shipping'] ?? [];
        $b = $o['billing'] ?? [];
        $meta = collect($o['meta_data'] ?? [])->pluck('value', 'key');
        $requiresShipping = self::requiresShipping($o);
        $cents = fn ($v) => (int) round(((float) $v) * 100);

        // Endereço: entrega se preenchido, senão cobrança (padrão do Woo).
        $addr = ! empty($s['address_1']) ? $s : $b;
        $prefix = ! empty($s['address_1']) ? '_shipping' : '_billing';

        return [
            'external_id' => (string) $o['id'],
            'external_number' => (string) ($o['number'] ?? $o['id']),
            'status' => self::status((string) $o['status'], $requiresShipping),
            'payment_status' => self::paymentStatus((string) $o['status']),
            'payment_method' => $o['payment_method'] ?? null,
            'channel_status' => $o['status'] ?? null,
            'subtotal_cents' => $cents($o['total']) - $cents($o['shipping_total'] ?? 0) + $cents($o['discount_total'] ?? 0),
            'shipping_cents' => $cents($o['shipping_total'] ?? 0),
            'discount_cents' => $cents($o['discount_total'] ?? 0),
            'total_cents' => $cents($o['total']),
            'currency' => $o['currency'] ?? 'BRL',
            'ship_name' => trim(($addr['first_name'] ?? '') . ' ' . ($addr['last_name'] ?? '')) ?: null,
            'ship_street' => $addr['address_1'] ?? null,
            'ship_number' => $meta["{$prefix}_number"] ?? null,
            'ship_complement' => $addr['address_2'] ?? null,
            'ship_district' => $meta["{$prefix}_neighborhood"] ?? null,
            'ship_city' => $addr['city'] ?? null,
            'ship_state' => $addr['state'] ?? null,
            'ship_zip' => isset($addr['postcode']) ? preg_replace('/\D/', '', $addr['postcode']) : null,
            'requires_shipping' => $requiresShipping,
            'placed_at' => isset($o['date_created_gmt']) ? CarbonImmutable::parse($o['date_created_gmt'], 'UTC') : null,
            'paid_at' => isset($o['date_paid_gmt']) ? CarbonImmutable::parse($o['date_paid_gmt'], 'UTC') : null,
            'notes' => $o['customer_note'] ?? null,
            'raw' => $o,
        ];
    }

    /** @return array<int, array> */
    public static function items(array $o): array
    {
        $cents = fn ($v) => (int) round(((float) $v) * 100);

        return collect($o['line_items'] ?? [])->map(fn ($li) => [
            'name' => $li['name'],
            'external_sku' => $li['sku'] ?: (string) ($li['product_id'] ?? ''),
            'external_product_id' => (string) ($li['product_id'] ?? ''),
            'quantity' => (int) $li['quantity'],
            'unit_cents' => $li['quantity'] ? intdiv($cents($li['total']), (int) $li['quantity']) : $cents($li['total']),
            'total_cents' => $cents($li['total']),
        ])->all();
    }
}
