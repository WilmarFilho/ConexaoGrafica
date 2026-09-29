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
 * - Um produto do hub por livro, ligado por ProductChannelRef.
 * - Livro com formatos (produto variável na loja): o livro e cada variação
 *   apontam para o mesmo produto do hub; o vínculo guarda qual formato é
 *   aquele id, e o produto lista os formatos à venda.
 * - Peso e medidas: o que a loja tem preenchido vence; o que está vazio na
 *   loja não apaga o que alguém digitou no hub.
 * - E-book = virtual/baixável no Woo (não entra na expedição).
 * - No fim, religa os itens de pedido que ainda não apontavam para produto e
 *   anota em cada item o formato vendido.
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
                $variations = ($payload['type'] ?? 'simple') === 'variable'
                    ? $this->client->variations((int) $payload['id'])
                    : [];

                $this->upsert($payload, $weightUnit, $dimUnit, $variations);
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

    /**
     * @param  array  $variations  variações do produto na loja (vazio = produto simples)
     */
    public function upsert(array $p, string $weightUnit = 'kg', string $dimUnit = 'cm', array $variations = []): Product
    {
        return DB::transaction(function () use ($p, $weightUnit, $dimUnit, $variations) {
            $parentId = (string) $p['id'];

            // id da loja => formato daquele id (null = o livro em si, com variações)
            $formatsById = [];

            if ($variations) {
                $formatsById[$parentId] = null;

                foreach ($variations as $v) {
                    $label = collect($v['attributes'] ?? [])->pluck('option')->filter()->implode(' ');
                    $formatsById[(string) $v['id']] = Product::formatFromLabel($label, (bool) ($v['virtual'] ?? false));
                }

                // formato sem preço não está à venda
                $onSale = collect($variations)
                    ->filter(fn ($v) => (string) ($v['price'] ?? '') !== '')
                    ->map(fn ($v) => $formatsById[(string) $v['id']])
                    ->all();

                $shipped = collect($variations)
                    ->first(fn ($v) => in_array($formatsById[(string) $v['id']], Product::SHIPPED_FORMATS, true));
            } else {
                $single = Product::formatFromLabel(null, (bool) ($p['virtual'] ?? false) || (bool) ($p['downloadable'] ?? false));
                $formatsById[$parentId] = $single;
                $onSale = [$single];
                $shipped = null;
            }

            $product = $this->findOrMerge(array_keys($formatsById), $p);

            $data = [
                'name' => Product::cleanTitle((string) $p['name']),
                'formats' => $onSale,
                'active' => ($p['status'] ?? 'publish') === 'publish',
            ];

            if (! $product->exists) {
                $data['sku'] = $this->uniqueSku($p['sku'] ?: 'WOO-'.$parentId);
            }

            if ($isbn = $this->isbnFrom($p)) {
                $data['isbn'] = $isbn;
            }

            // Medidas: as do impresso, se a variação tiver; senão as do livro.
            // Só sobrescreve quando a loja tem valor.
            $weight = ($shipped['weight'] ?? '') !== '' ? $shipped['weight'] : ($p['weight'] ?? null);

            if ($grams = $this->grams($weight, $weightUnit)) {
                $data['weight_grams'] = $grams;
            }

            foreach (['width' => 'width_cm', 'height' => 'height_cm', 'length' => 'depth_cm'] as $from => $to) {
                $value = ($shipped['dimensions'][$from] ?? '') !== '' ? $shipped['dimensions'][$from] : ($p['dimensions'][$from] ?? null);

                if ($cm = $this->cm($value, $dimUnit)) {
                    $data[$to] = $cm;
                }
            }

            $stock = ($shipped && ($shipped['manage_stock'] ?? false) === true) ? $shipped : $p;

            if (($stock['manage_stock'] ?? false) === true && isset($stock['stock_quantity'])) {
                $data['stock_physical'] = (int) $stock['stock_quantity'];
            }

            $product->fill($data)->save();

            $skus = collect($variations)->pluck('sku', 'id')->put($parentId, $p['sku'] ?? '');

            foreach ($formatsById as $externalId => $format) {
                ProductChannelRef::updateOrCreate(
                    ['channel_id' => $this->channel->id, 'external_id' => (string) $externalId],
                    ['product_id' => $product->id, 'external_sku' => ($skus[$externalId] ?? '') ?: null, 'format' => $format],
                );
            }

            return $product;
        });
    }

    /**
     * O produto do hub para este livro. Quando cada formato já tinha virado um
     * produto separado, junta tudo no primeiro físico (ou no mais antigo):
     * itens de pedido e vínculos passam para ele e os outros deixam de existir.
     */
    private function findOrMerge(array $externalIds, array $p): Product
    {
        $candidates = Product::query()
            ->whereIn('id', ProductChannelRef::query()
                ->where('channel_id', $this->channel->id)
                ->whereIn('external_id', array_map('strval', $externalIds))
                ->select('product_id'))
            ->orderByDesc('physical')
            ->orderBy('id')
            ->get();

        $keeper = $candidates->first() ?? $this->matchBySku($p) ?? new Product;

        foreach ($candidates->skip(1) as $other) {
            OrderItem::query()->where('product_id', $other->id)->update(['product_id' => $keeper->id]);
            ProductChannelRef::query()->where('product_id', $other->id)->update(['product_id' => $keeper->id]);

            foreach (['weight_grams', 'width_cm', 'height_cm', 'depth_cm', 'isbn'] as $field) {
                if (blank($keeper->{$field}) && filled($other->{$field})) {
                    $keeper->{$field} = $other->{$field};
                }
            }

            $other->delete();
        }

        return $keeper;
    }

    /**
     * Itens de pedido sem produto ou sem formato: casa pelo SKU ou pelo id do
     * produto no canal. Conta só os que ganharam produto.
     */
    public function relinkOrderItems(): int
    {
        $refs = ProductChannelRef::query()->where('channel_id', $this->channel->id)->get();
        $bySku = $refs->whereNotNull('external_sku')->keyBy('external_sku');
        $byId = $refs->keyBy('external_id');

        // vínculo antigo, sem formato, de produto que só tem um: vale esse
        $single = Product::query()->whereIn('id', $refs->pluck('product_id')->unique())->get(['id', 'formats'])
            ->filter(fn (Product $p) => count($p->formats ?? []) === 1)
            ->mapWithKeys(fn (Product $p) => [$p->id => $p->formats[0]]);

        $n = 0;

        OrderItem::query()
            ->where(fn ($q) => $q->whereNull('product_id')->orWhereNull('format'))
            ->whereHas('order', fn ($q) => $q->where('channel_id', $this->channel->id))
            ->chunkById(200, function ($items) use ($bySku, $byId, $single, &$n) {
                foreach ($items as $item) {
                    $ref = $byId[$item->external_sku] ?? $bySku[$item->external_sku] ?? null;

                    if (! $ref) {
                        continue;
                    }

                    $changes = [];

                    if (! $item->product_id) {
                        $changes['product_id'] = $ref->product_id;
                        $n++;
                    }

                    $format = $ref->format ?? $single[$ref->product_id] ?? null;

                    if (! $item->format && $format) {
                        $changes['format'] = $format;
                    }

                    if ($changes) {
                        $item->update($changes);
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

    private function grams(mixed $value, string $unit): ?int
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

    private function cm(mixed $value, string $unit): ?int
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
