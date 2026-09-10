<?php

namespace App\Integrations\Bling;

use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;

/**
 * Pedido de venda do Bling (detalhe da API v3) → formato interno do hub.
 * Usado para os pedidos da Amazon, que chegam ao Bling pela integração
 * de marketplace e trazem o número da Amazon em numeroLoja.
 */
class OrderMapper
{
    /** Número de pedido da Amazon: 3-7-7 dígitos (ex.: 701-3392-4410 é abreviação; o real é 701-1234567-1234567). */
    public static function isAmazonNumber(?string $n): bool
    {
        return (bool) preg_match('/^\d{3}-\d{7}-\d{7}$/', (string) $n);
    }

    /**
     * Situações padrão do Bling: 6 Em aberto, 9 Atendido, 12 Cancelado,
     * 15 Em andamento, 18 Venda agenciada, 21 Em digitação, 24 Verificado.
     * Pedido de marketplace já chega pago; "Atendido" = enviado.
     */
    public static function status(int $situacao, bool $requiresShipping): OrderStatus
    {
        return match ($situacao) {
            12 => OrderStatus::Cancelled,
            9 => $requiresShipping ? OrderStatus::Shipped : OrderStatus::Fulfilled,
            21 => OrderStatus::New,
            default => $requiresShipping ? OrderStatus::Paid : OrderStatus::Fulfilled,
        };
    }

    public static function paymentStatus(int $situacao): string
    {
        return $situacao === 12 ? 'failed' : 'paid';
    }

    public static function customer(array $o): array
    {
        $c = $o['contato'] ?? [];

        return [
            'name' => $c['nome'] ?? 'Comprador Amazon',
            'email' => $c['email'] ?? null,
            'phone' => $c['celular'] ?? $c['telefone'] ?? null,
            'document' => preg_replace('/\D/', '', (string) ($c['numeroDocumento'] ?? '')) ?: null,
        ];
    }

    public static function order(array $o): array
    {
        $cents = fn ($v) => (int) round(((float) $v) * 100);
        $t = $o['transporte'] ?? [];
        $e = $t['etiqueta'] ?? [];
        $situacao = (int) ($o['situacao']['id'] ?? $o['situacao']['valor'] ?? 6);
        $requiresShipping = ! empty($e['cep']) || ! empty($e['endereco']);

        return [
            'external_id' => (string) $o['id'],
            'external_number' => (string) ($o['numeroLoja'] ?: $o['numero']),
            'status' => self::status($situacao, $requiresShipping),
            'payment_status' => self::paymentStatus($situacao),
            'payment_method' => 'amazon',
            'channel_status' => 'bling:'.$situacao,
            'subtotal_cents' => $cents($o['totalProdutos'] ?? 0),
            'shipping_cents' => $cents($t['frete'] ?? 0),
            'discount_cents' => $cents($o['desconto']['valor'] ?? 0),
            'total_cents' => $cents($o['total'] ?? 0),
            'currency' => 'BRL',
            'requires_shipping' => $requiresShipping,
            'ship_name' => $e['nome'] ?? null,
            'ship_street' => $e['endereco'] ?? null,
            'ship_number' => $e['numero'] ?? null,
            'ship_complement' => $e['complemento'] ?? null,
            'ship_district' => $e['bairro'] ?? null,
            'ship_city' => $e['municipio'] ?? null,
            'ship_state' => $e['uf'] ?? null,
            'ship_zip' => $e['cep'] ?? null,
            'placed_at' => ! empty($o['data']) ? CarbonImmutable::parse($o['data']) : now(),
            'paid_at' => ! empty($o['data']) ? CarbonImmutable::parse($o['data']) : now(),
            'raw' => $o,
        ];
    }

    public static function items(array $o): array
    {
        $cents = fn ($v) => (int) round(((float) $v) * 100);

        return collect($o['itens'] ?? [])->map(fn ($i) => [
            'name' => $i['descricao'] ?? 'Item',
            'external_sku' => $i['codigo'] ?: (string) ($i['produto']['id'] ?? ''),
            'external_product_id' => (string) ($i['produto']['id'] ?? ''),
            'quantity' => (int) ($i['quantidade'] ?? 1),
            'unit_cents' => $cents($i['valor'] ?? 0),
            'total_cents' => $cents(((float) ($i['valor'] ?? 0)) * (int) ($i['quantidade'] ?? 1)),
        ])->all();
    }
}
