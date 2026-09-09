<?php

namespace App\Integrations\WooCommerce;

use App\Models\Channel;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductChannelRef;
use App\Models\SyncLog;
use Illuminate\Support\Facades\DB;

/**
 * Traz o catálogo do WooCommerce para a tabela de produtos do hub.
 *
 * - Um produto do hub por produto do Woo, ligado por ProductChannelRef.
 * - Peso e medidas: o que a loja tem preenchido vence; o que está vazio na
 *   loja não apaga o que alguém digitou no hub.
 * - E-book = produto virtual/baixável no Woo (não entra na expedição).
 * - No fim, religa os itens de pedido que ainda não apontavam para produto.
 */
class ImportProducts
{
    public function __construct(
        private readonly WooCommerceClient $client,
        private readonly Channel $channel,
    ) {}

    public static function make(): self
    {
        return new self(WooCommerceClient::fromConfig(), Channel::bySlug(Channel::WOOCOMMERCE));
    }

    /** @return array{products:int, relinked:int} */
    public function all(): array
    {
        $weightUnit = $this->client->setting('products', 'woocommerce_weight_unit') ?: 'kg';
        $dimUnit = $this->client->setting('products', 'woocommerce_dimension_unit') ?: 'cm';
        $count = 0;

        try {
            foreach ($this->client->products() as $payload) {
                $this->upsert($payload, $weightUnit, $dimUnit);
                $count++;
            }

            $relinked = $this->relinkOrderItems();

            SyncLog::record($this->channel, 'in', 'products.sync', null, "{$count} produto(s) sincronizado(s), {$relinked} item(ns) de pedido religado(s)");

            return ['products' => $count, 'relinked' => $relinked];
        } catch (\Throwable $e) {
            SyncLog::record($this->channel, 'in', 'products.sync', null, $e->getMessage(), [], 'error');
            throw $e;
        }
    }

    public function upsert(array $p, string $weightUnit = 'kg', string $dimUnit = 'cm'): Product
    {
        return DB::transaction(function () use ($p, $weightUnit, $dimUnit) {
            $externalId = (string) $p['id'];
            $ref = ProductChannelRef::query()
                ->where('channel_id', $this->channel->id)
                ->where('external_id', $externalId)
                ->first();

            $product = $ref?->product ?? $this->matchBySku($p) ?? new Product;

            $physical = ! ($p['virtual'] ?? false) && ! ($p['downloadable'] ?? false);
            $data = [
                'name' => $p['name'],
                'physical' => $physical,
                'active' => ($p['status'] ?? 'publish') === 'publish',
            ];

            if (! $product->exists) {
                $data['sku'] = $this->uniqueSku($p['sku'] ?: 'WOO-'.$externalId);
            }

            if ($isbn = $this->isbnFrom($p)) {
                $data['isbn'] = $isbn;
            }

            // Medidas: só sobrescreve quando a loja tem valor.
            if ($grams = $this->grams($p['weight'] ?? null, $weightUnit)) {
                $data['weight_grams'] = $grams;
            }
            foreach (['width' => 'width_cm', 'height' => 'height_cm', 'length' => 'depth_cm'] as $from => $to) {
                if ($cm = $this->cm($p['dimensions'][$from] ?? null, $dimUnit)) {
                    $data[$to] = $cm;
                }
            }

            if (($p['manage_stock'] ?? false) && isset($p['stock_quantity'])) {
                $data['stock_physical'] = (int) $p['stock_quantity'];
            }

            $product->fill($data)->save();

            ProductChannelRef::updateOrCreate(
                ['channel_id' => $this->channel->id, 'external_id' => $externalId],
                ['product_id' => $product->id, 'external_sku' => $p['sku'] ?: null],
            );

            return $product;
        });
    }

    /** Itens de pedido sem produto: casa pelo SKU ou pelo id do produto no canal. */
    public function relinkOrderItems(): int
    {
        $refs = ProductChannelRef::query()->where('channel_id', $this->channel->id)->get();
        $bySku = $refs->whereNotNull('external_sku')->keyBy('external_sku');
        $byId = $refs->keyBy('external_id');
        $n = 0;

        OrderItem::query()
            ->whereNull('product_id')
            ->whereHas('order', fn ($q) => $q->where('channel_id', $this->channel->id))
            ->chunkById(200, function ($items) use ($bySku, $byId, &$n) {
                foreach ($items as $item) {
                    $ref = $bySku[$item->external_sku] ?? $byId[$item->external_sku] ?? null;
                    if ($ref) {
                        $item->update(['product_id' => $ref->product_id]);
                        $n++;
                    }
                }
            });

        return $n;
    }

    private function matchBySku(array $p): ?Product
    {
        return ! empty($p['sku']) ? Product::query()->where('sku', $p['sku'])->first() : null;
    }

    private function uniqueSku(string $sku): string
    {
        $base = mb_substr($sku, 0, 55);
        $candidate = $base;
        $i = 2;
        while (Product::query()->where('sku', $candidate)->exists()) {
            $candidate = $base.'-'.$i++;
        }

        return $candidate;
    }

    private function isbnFrom(array $p): ?string
    {
        foreach ($p['attributes'] ?? [] as $attr) {
            if (stripos((string) ($attr['name'] ?? ''), 'isbn') !== false) {
                $v = preg_replace('/[^0-9Xx]/', '', implode('', $attr['options'] ?? []));
                if (strlen($v) >= 10) {
                    return mb_substr($v, 0, 20);
                }
            }
        }
        foreach ($p['meta_data'] ?? [] as $m) {
            if (stripos((string) ($m['key'] ?? ''), 'isbn') !== false && is_string($m['value'] ?? null)) {
                $v = preg_replace('/[^0-9Xx]/', '', $m['value']);
                if (strlen($v) >= 10) {
                    return mb_substr($v, 0, 20);
                }
            }
        }

        return null;
    }

    private function grams(?string $value, string $unit): ?int
    {
        $v = (float) str_replace(',', '.', (string) $value);
        if ($v <= 0) {
            return null;
        }

        return (int) round(match ($unit) {
            'g' => $v,
            'lbs' => $v * 453.592,
            'oz' => $v * 28.3495,
            default => $v * 1000, // kg
        });
    }

    private function cm(?string $value, string $unit): ?int
    {
        $v = (float) str_replace(',', '.', (string) $value);
        if ($v <= 0) {
            return null;
        }

        return max(1, (int) ceil(match ($unit) {
            'mm' => $v / 10,
            'm' => $v * 100,
            'in' => $v * 2.54,
            default => $v, // cm
        }));
    }
}
