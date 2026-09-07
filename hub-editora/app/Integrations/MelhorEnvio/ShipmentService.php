<?php

namespace App\Integrations\MelhorEnvio;

use App\Enums\OrderStatus;
use App\Jobs\NotifyChannelShipped;
use App\Models\Channel;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\SyncLog;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Regras de expedição por cima do cliente do Melhor Envio: valida o pedido,
 * monta volumes, cota, compra, gera a etiqueta e mantém Order/Shipment
 * coerentes. Toda falha vira `problem` com motivo legível, nunca exceção
 * solta na tela.
 */
class ShipmentService
{
    private ?MelhorEnvioClient $client = null;

    public function __construct(?MelhorEnvioClient $client = null)
    {
        $this->client = $client;
    }

    private function client(): MelhorEnvioClient
    {
        return $this->client ??= MelhorEnvioClient::make();
    }

    private function channel(): ?Channel
    {
        return Channel::bySlug(Channel::MELHOR_ENVIO);
    }

    // ---- Validação -----------------------------------------------------

    /** Lista de pendências que impedem gerar etiqueta. Vazio = pode seguir. */
    public function issues(Order $order): array
    {
        $issues = [];

        if (! $order->requires_shipping) {
            $issues[] = 'Pedido digital: nada a expedir';
        }
        if ($order->payment_status !== 'paid') {
            $issues[] = 'Pagamento não confirmado';
        }

        $zip = preg_replace('/\D/', '', (string) $order->ship_zip);
        if (strlen($zip) !== 8) {
            $issues[] = $zip === '' ? 'CEP ausente' : 'CEP inválido: informado com '.strlen($zip).' dígitos ('.$order->ship_zip.')';
        }
        if (! $order->ship_street || ! $order->ship_city || ! $order->ship_state) {
            $issues[] = 'Endereço incompleto';
        }
        if (! $order->ship_number) {
            $issues[] = 'Endereço sem número';
        }
        if (! $order->customer?->document && ! $order->customer?->phone) {
            $issues[] = 'Cliente sem CPF/CNPJ e sem telefone';
        }
        if (! MelhorEnvioClient::isConfigured()) {
            $issues[] = 'Melhor Envio não configurado (ME_TOKEN / ME_FROM_ZIP)';
        }

        return $issues;
    }

    // ---- Cotação -------------------------------------------------------

    /**
     * Cota o pedido e devolve as opções válidas, mais barata primeiro:
     * [['id'=>1,'name'=>'PAC','company'=>'Correios','price_cents'=>1870,'days'=>6], ...]
     */
    public function quote(Order $order): array
    {
        $cfg = config('hub.melhor_envio');
        $products = $this->productsForQuote($order);

        $payload = [
            'from' => ['postal_code' => preg_replace('/\D/', '', (string) $cfg['from']['postal_code'])],
            'to' => ['postal_code' => preg_replace('/\D/', '', (string) $order->ship_zip)],
            'products' => $products,
            'options' => ['receipt' => false, 'own_hand' => false, 'insurance_value' => $this->insuranceValue($order)],
        ];
        if (! empty($cfg['services'])) {
            $payload['services'] = implode(',', $cfg['services']);
        }

        $raw = $this->client()->calculate($payload);

        return collect($raw)
            ->filter(fn ($s) => empty($s['error']) && isset($s['price']))
            ->map(fn ($s) => [
                'id' => (int) $s['id'],
                'name' => $s['name'],
                'company' => $s['company']['name'] ?? '',
                'price_cents' => (int) round(((float) ($s['custom_price'] ?? $s['price'])) * 100),
                'days' => (int) ($s['custom_delivery_time'] ?? $s['delivery_time'] ?? 0),
            ])
            ->sortBy('price_cents')
            ->values()
            ->all();
    }

    // ---- Compra + etiqueta --------------------------------------------

    /**
     * Fluxo completo para um pedido: carrinho → checkout → gerar → imprimir.
     * $serviceId nulo = mais barato da cotação.
     */
    public function buy(Order $order, ?int $serviceId = null): Shipment
    {
        $order->loadMissing(['customer', 'items', 'channel']);

        if ($issues = $this->issues($order)) {
            return $this->fail($order, implode('; ', $issues));
        }

        $shipment = $order->shipment()->firstOrNew([]);
        if ($shipment->exists && in_array($shipment->status, [Shipment::LABEL_GENERATED, Shipment::SHIPPED, Shipment::DELIVERED], true)) {
            return $shipment; // idempotente: nunca compra duas vezes
        }

        try {
            $options = $this->quote($order);
            $chosen = $serviceId
                ? collect($options)->firstWhere('id', $serviceId)
                : ($options[0] ?? null);

            if (! $chosen) {
                throw new RuntimeException('Nenhum serviço disponível para este CEP/pacote.');
            }

            $cart = $this->client()->addToCart($this->cartPayload($order, $chosen['id']));
            $meId = $cart['id'] ?? throw new RuntimeException('Carrinho sem id de envio.');

            $shipment->fill([
                'order_id' => $order->id,
                'status' => Shipment::QUOTED,
                'carrier' => $chosen['company'],
                'service' => $chosen['name'],
                'cost_cents' => $chosen['price_cents'],
                'melhor_envio_id' => $meId,
                'raw' => ['cart' => $cart],
            ])->save();

            $this->client()->checkout([$meId]);
            $shipment->update(['status' => Shipment::PURCHASED]);

            $gen = $this->client()->generate([$meId]);
            if (isset($gen[$meId]['status']) && $gen[$meId]['status'] === false) {
                throw new RuntimeException('Falha ao gerar etiqueta: '.($gen[$meId]['message'] ?? 'sem detalhe'));
            }

            $labelUrl = $this->client()->printUrl([$meId]);
            $tracking = $this->client()->tracking([$meId])[$meId] ?? [];

            DB::transaction(function () use ($shipment, $labelUrl, $tracking, $order) {
                $shipment->update([
                    'status' => Shipment::LABEL_GENERATED,
                    'label_url' => $labelUrl,
                    'tracking_code' => $tracking['tracking'] ?? $shipment->tracking_code,
                    'label_generated_at' => now(),
                    'problem' => null,
                    'raw' => array_merge($shipment->raw ?? [], ['tracking' => $tracking]),
                ]);
                $order->update(['status' => OrderStatus::LabelGenerated]);
            });

            SyncLog::record($this->channel(), 'out', 'label.generated', $order,
                "Etiqueta {$chosen['company']} {$chosen['name']} gerada", ['me_id' => $meId, 'cost_cents' => $chosen['price_cents']]);

            return $shipment->refresh();
        } catch (Throwable $e) {
            return $this->fail($order, $e->getMessage(), $shipment);
        }
    }

    /** Atualiza rastreio/situação de um envio já comprado; move o pedido quando postado/entregue. */
    public function refreshTracking(Shipment $shipment): void
    {
        if (! $shipment->melhor_envio_id) {
            return;
        }

        $t = $this->client()->tracking([$shipment->melhor_envio_id])[$shipment->melhor_envio_id] ?? null;
        if (! $t) {
            return;
        }

        $order = $shipment->order;
        $updates = ['raw' => array_merge($shipment->raw ?? [], ['tracking' => $t])];

        if (! empty($t['tracking']) && $t['tracking'] !== $shipment->tracking_code) {
            $updates['tracking_code'] = $t['tracking'];
        }

        if (! empty($t['delivered_at'])) {
            $updates['status'] = Shipment::DELIVERED;
            $updates['delivered_at'] = $t['delivered_at'];
            $order->update(['status' => OrderStatus::Delivered]);
        } elseif (! empty($t['posted_at']) && $shipment->status !== Shipment::SHIPPED) {
            $updates['status'] = Shipment::SHIPPED;
            $updates['shipped_at'] = $t['posted_at'];
            $order->update(['status' => OrderStatus::Shipped]);
        } elseif (! empty($t['canceled_at'])) {
            $updates['status'] = Shipment::CANCELLED;
        }

        $shipment->update($updates);

        // Avisa o canal uma única vez, assim que houver rastreio.
        if ($shipment->tracking_code && ! $shipment->channel_notified) {
            NotifyChannelShipped::dispatch($shipment->id);
        }
    }

    // ---- Montagem dos payloads ----------------------------------------

    private function fail(Order $order, string $reason, ?Shipment $shipment = null): Shipment
    {
        $shipment ??= $order->shipment()->firstOrNew([]);
        $shipment->fill([
            'order_id' => $order->id,
            'status' => Shipment::PROBLEM,
            'problem' => mb_substr($reason, 0, 1000),
        ])->save();

        $order->update(['status' => OrderStatus::Problem]);
        SyncLog::record($this->channel(), 'out', 'label.failed', $order, $reason, [], 'error');

        return $shipment;
    }

    /** Volumes por item, usando dimensões do produto ou o pacote padrão. */
    private function productsForQuote(Order $order): array
    {
        $def = config('hub.melhor_envio.default_package');

        return $order->items->map(function (OrderItem $item) use ($def) {
            $p = $item->product_id ? Product::find($item->product_id) : null;
            $ok = $p?->hasShippingDimensions();

            return [
                'id' => (string) ($item->external_sku ?: $item->id),
                'width' => $ok ? $p->width_cm : $def['width_cm'],
                'height' => $ok ? $p->height_cm : $def['height_cm'],
                'length' => $ok ? $p->depth_cm : $def['depth_cm'],
                'weight' => round(($ok ? $p->weight_grams : $def['weight_grams']) / 1000, 3),
                'insurance_value' => round($item->unit_cents / 100, 2),
                'quantity' => (int) $item->quantity,
            ];
        })->values()->all();
    }

    private function insuranceValue(Order $order): float
    {
        return round(max(0, $order->subtotal_cents) / 100, 2);
    }

    private function cartPayload(Order $order, int $serviceId): array
    {
        $cfg = config('hub.melhor_envio');
        $from = $cfg['from'];
        $c = $order->customer;
        $doc = preg_replace('/\D/', '', (string) $c?->document);

        $to = [
            'name' => $order->ship_name ?: $c?->name,
            'phone' => preg_replace('/\D/', '', (string) $c?->phone),
            'email' => $c?->email,
            'address' => $order->ship_street,
            'complement' => $order->ship_complement,
            'number' => (string) $order->ship_number,
            'district' => $order->ship_district,
            'city' => $order->ship_city,
            'state_abbr' => $order->ship_state,
            'country_id' => 'BR',
            'postal_code' => preg_replace('/\D/', '', (string) $order->ship_zip),
            'note' => $order->ship_reference,
        ];
        if (strlen($doc) === 14) {
            $to['company_document'] = $doc;
        } else {
            $to['document'] = $doc;
        }

        $products = $order->items->map(fn (OrderItem $i) => [
            'name' => mb_substr($i->name, 0, 120),
            'quantity' => (int) $i->quantity,
            'unitary_value' => round($i->unit_cents / 100, 2),
        ])->values()->all();

        // Um volume por pedido: soma dos pesos, maior base, alturas somadas.
        $vols = $this->productsForQuote($order);
        $volume = [
            'width' => max(array_column($vols, 'width')),
            'length' => max(array_column($vols, 'length')),
            'height' => max(1, (int) ceil(array_sum(array_map(fn ($v) => $v['height'] * $v['quantity'] / max(1, count($vols)), $vols)))),
            'weight' => round(array_sum(array_map(fn ($v) => $v['weight'] * $v['quantity'], $vols)), 3),
        ];

        return [
            'service' => $serviceId,
            'from' => array_filter([
                'name' => $from['name'],
                'phone' => preg_replace('/\D/', '', (string) $from['phone']),
                'email' => $from['email'],
                'document' => $from['document'],
                'company_document' => $from['company_document'],
                'address' => $from['address'],
                'complement' => $from['complement'],
                'number' => $from['number'],
                'district' => $from['district'],
                'city' => $from['city'],
                'state_abbr' => $from['state'],
                'country_id' => 'BR',
                'postal_code' => preg_replace('/\D/', '', (string) $from['postal_code']),
            ], fn ($v) => $v !== null && $v !== ''),
            'to' => array_filter($to, fn ($v) => $v !== null && $v !== ''),
            'products' => $products,
            'volumes' => [$volume],
            'options' => [
                'insurance_value' => $this->insuranceValue($order),
                'receipt' => false,
                'own_hand' => false,
                'reverse' => false,
                'non_commercial' => (bool) $cfg['non_commercial'],
                'platform' => 'Hub Editora',
                'tags' => [[
                    'tag' => ($order->channel?->name ?? 'Pedido').' #'.($order->external_number ?: $order->external_id),
                    'url' => null,
                ]],
            ],
        ];
    }
}
