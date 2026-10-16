<?php

namespace App\Integrations\Amazon;

use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;

/**
 * Pedido da SP-API (Orders v0) → formato interno do hub.
 *
 * O endereço e o comprador chegam só pelo token de dados restritos e vão
 * apenas para as colunas do pedido e do cliente (que a retenção de 30 dias
 * apaga). O "raw" guardado não leva esses blocos.
 */
class OrderMapper
{
    /** Blocos com dados pessoais que nunca vão para o raw. */
    public const PII_KEYS = ['ShippingAddress', 'BuyerInfo', 'DefaultShipFromLocationAddress'];

    public static function status(string $amazon, bool $requiresShipping): OrderStatus
    {
        return match ($amazon) {
            'Canceled', 'Unfulfillable' => OrderStatus::Cancelled,
            'Pending', 'PendingAvailability' => OrderStatus::AwaitingPayment,
            'Shipped', 'InvoiceUnconfirmed' => $requiresShipping ? OrderStatus::Shipped : OrderStatus::Fulfilled,
            default => $requiresShipping ? OrderStatus::Paid : OrderStatus::Fulfilled, // Unshipped, PartiallyShipped
        };
    }

    public static function paymentStatus(string $amazon): string
    {
        return match ($amazon) {
            'Canceled', 'Unfulfillable' => 'failed',
            'Pending', 'PendingAvailability' => 'pending',
            default => 'paid',
        };
    }

    public static function customer(array $o): array
    {
        $b = $o['BuyerInfo'] ?? [];
        $a = $o['ShippingAddress'] ?? [];
        $doc = null;

        foreach ($b['BuyerTaxInfo']['TaxClassifications'] ?? [] as $tax) {
            if (in_array(mb_strtoupper((string) ($tax['Name'] ?? '')), ['CPF', 'CNPJ'], true)) {
                $doc = preg_replace('/\D/', '', (string) ($tax['Value'] ?? '')) ?: null;
            }
        }

        return [
            'name' => $b['BuyerName'] ?? $a['Name'] ?? 'Comprador Amazon',
            'email' => $b['BuyerEmail'] ?? null,
            'phone' => $a['Phone'] ?? null,
            'document' => $doc,
        ];
    }

    /**
     * Endereço brasileiro costuma vir com o número na primeira linha
     * ("Rua 227-A, 20"); separamos para a etiqueta.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function streetAndNumber(?string $line1, ?string $line2): array
    {
        $line1 = trim((string) $line1);

        if ($line1 !== '' && preg_match('/^(.*\D)[,\s]+n?[ºo°.]*\s*(\d+[A-Za-z]?)$/u', $line1, $m)) {
            return [rtrim(trim($m[1]), ','), $m[2]];
        }

        $line2 = trim((string) $line2);

        if (preg_match('/^(\d+[A-Za-z]?)\b/', $line2, $m)) {
            return [$line1 ?: null, $m[1]];
        }

        return [$line1 ?: null, null];
    }

    /** "Goiás", "GO" ou "goias" → "GO" (a coluna guarda só a sigla). */
    public static function uf(?string $state): ?string
    {
        $s = trim((string) $state);

        if ($s === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z]{2}$/', $s)) {
            return mb_strtoupper($s);
        }

        $nomes = [
            'acre' => 'AC', 'alagoas' => 'AL', 'amapa' => 'AP', 'amazonas' => 'AM', 'bahia' => 'BA', 'ceara' => 'CE',
            'distrito federal' => 'DF', 'espirito santo' => 'ES', 'goias' => 'GO', 'maranhao' => 'MA', 'mato grosso' => 'MT',
            'mato grosso do sul' => 'MS', 'minas gerais' => 'MG', 'para' => 'PA', 'paraiba' => 'PB', 'parana' => 'PR',
            'pernambuco' => 'PE', 'piaui' => 'PI', 'rio de janeiro' => 'RJ', 'rio grande do norte' => 'RN',
            'rio grande do sul' => 'RS', 'rondonia' => 'RO', 'roraima' => 'RR', 'santa catarina' => 'SC',
            'sao paulo' => 'SP', 'sergipe' => 'SE', 'tocantins' => 'TO',
        ];
        $chave = mb_strtolower(trim(\Illuminate\Support\Str::ascii($s)));

        return $nomes[$chave] ?? null;
    }

    public static function order(array $o): array
    {
        $cents = fn ($money) => (int) round(((float) ($money['Amount'] ?? 0)) * 100);
        $a = $o['ShippingAddress'] ?? [];
        $status = (string) ($o['OrderStatus'] ?? 'Pending');
        $requiresShipping = ($o['FulfillmentChannel'] ?? 'MFN') === 'MFN';
        [$street, $number] = self::streetAndNumber($a['AddressLine1'] ?? null, $a['AddressLine2'] ?? null);
        $complement = $number !== null && preg_match('/^\d/', (string) ($a['AddressLine2'] ?? ''))
            ? null
            : ($a['AddressLine2'] ?? null);
        $placed = ! empty($o['PurchaseDate']) ? CarbonImmutable::parse($o['PurchaseDate']) : now();

        return [
            'external_id' => (string) $o['AmazonOrderId'],
            'external_number' => (string) $o['AmazonOrderId'],
            'status' => self::status($status, $requiresShipping),
            'payment_status' => self::paymentStatus($status),
            'payment_method' => 'amazon',
            'channel_status' => 'amazon:'.$status,
            'total_cents' => $cents($o['OrderTotal'] ?? []),
            'currency' => $o['OrderTotal']['CurrencyCode'] ?? 'BRL',
            'requires_shipping' => $requiresShipping,
            'ship_name' => $a['Name'] ?? null,
            'ship_street' => $street,
            'ship_number' => $number !== null ? mb_substr($number, 0, 20) : null,
            'ship_complement' => $complement,
            'ship_district' => $a['District'] ?? $a['AddressLine3'] ?? null,
            'ship_city' => $a['City'] ?? $a['Municipality'] ?? null,
            'ship_state' => self::uf($a['StateOrRegion'] ?? null),
            'ship_zip' => isset($a['PostalCode']) ? preg_replace('/\D/', '', (string) $a['PostalCode']) : null,
            'placed_at' => $placed,
            'paid_at' => self::paymentStatus($status) === 'paid' ? $placed : null,
            'raw' => array_diff_key($o, array_flip(self::PII_KEYS)),
        ];
    }

    /** Itens do getOrderItems, com subtotal, frete e desconto do pedido somados a partir deles. */
    public static function items(array $items): array
    {
        $cents = fn ($money) => (int) round(((float) ($money['Amount'] ?? 0)) * 100);

        return collect($items)->map(function ($i) use ($cents) {
            $qty = max(1, (int) ($i['QuantityOrdered'] ?? 1));
            $total = $cents($i['ItemPrice'] ?? []); // ItemPrice já é o total da linha

            return [
                'name' => $i['Title'] ?? 'Item',
                'external_sku' => (string) ($i['SellerSKU'] ?? $i['ASIN'] ?? ''),
                'external_product_id' => (string) ($i['SellerSKU'] ?? ''),
                'quantity' => $qty,
                'unit_cents' => intdiv($total, $qty),
                'total_cents' => $total,
                'shipping_cents' => $cents($i['ShippingPrice'] ?? []),
                'discount_cents' => $cents($i['PromotionDiscount'] ?? []) + $cents($i['ShippingDiscount'] ?? []),
            ];
        })->all();
    }
}
