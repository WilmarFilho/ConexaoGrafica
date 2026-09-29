<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\SyncLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan hub:remap-woocommerce mapa.json           (simulação)
 * php artisan hub:remap-woocommerce mapa.json --apply   (para valer)
 *
 * A loja mudou de endereço (Pubcon → Conexão Editora) e, com ela, os ids de
 * pedidos e produtos. Este comando troca os ids antigos pelos novos nos
 * registros do canal WooCommerce, para o hub continuar enxergando os mesmos
 * pedidos e produtos em vez de importar tudo de novo em duplicidade.
 *
 * O mapa é um JSON: {"orders": {"idAntigo": "idNovo"}, "products": {...}}.
 * O que não tiver par fica marcado como "pubcon-<id>", para nunca ser
 * confundido com um id da loja nova.
 */
class RemapWooCommerce extends Command
{
    protected $signature = 'hub:remap-woocommerce {file : JSON com o mapa de ids} {--apply : Grava as mudanças}';

    protected $description = 'Troca os ids de pedidos e produtos do WooCommerce após a mudança de loja';

    private const LEGACY = 'pubcon-';

    private const ACTION = 'store.remapped';

    public function handle(): int
    {
        $map = json_decode((string) @file_get_contents($this->argument('file')), true);

        if (! is_array($map) || ! isset($map['orders'], $map['products'])) {
            $this->error('Mapa inválido: esperado {"orders": {...}, "products": {...}}.');

            return self::FAILURE;
        }

        $channel = Channel::bySlug(Channel::WOOCOMMERCE);
        $apply = (bool) $this->option('apply');

        // A troca só pode acontecer uma vez: depois dela, um id novo pode ser
        // igual a um id antigo do mapa e seria trocado de novo por engano.
        if (SyncLog::query()->where('channel_id', $channel->id)->where('action', self::ACTION)->exists()) {
            $this->info('A troca de ids já foi aplicada neste hub; nada a fazer.');

            return self::SUCCESS;
        }

        $run = function () use ($map, $channel): array {
            $orders = $this->remap('orders', $channel->id, $map['orders']);
            $products = $this->remap('product_channel_refs', $channel->id, $map['products']);

            return [
                'pedidos' => $orders,
                'produtos' => $products,
                // só os itens dos pedidos trocados agora: rodar de novo não troca duas vezes
                'itens' => $this->remapItems($orders['ids'], $map['products']),
            ];
        };

        if ($apply) {
            $result = DB::transaction(function () use ($run, $channel) {
                $result = $run();

                SyncLog::record($channel, SyncLog::MANUAL, self::ACTION, null, sprintf(
                    'Loja mudou de endereço: %d pedido(s) e %d produto(s) com id trocado',
                    $result['pedidos']['mapped'],
                    $result['produtos']['mapped'],
                ));

                return $result;
            });
        } else {
            DB::beginTransaction();
            $result = $run();
            DB::rollBack();
        }

        foreach ($result as $what => $r) {
            $this->line(sprintf('%-9s %d trocado(s), %d sem par, %d já estavam certos', $what, $r['mapped'], $r['legacy'], $r['kept']));
        }

        $this->info($apply ? 'Ids trocados.' : 'Simulação: nada foi gravado. Use --apply para gravar.');

        return self::SUCCESS;
    }

    /**
     * Troca em duas passadas (id provisório, depois o definitivo): um id novo
     * pode coincidir com um id antigo de outro registro, e a tabela não aceita
     * repetição dentro do canal.
     *
     * @return array{mapped:int, legacy:int, kept:int, ids:array<int, int>}
     */
    private function remap(string $table, int $channelId, array $map): array
    {
        $stats = ['mapped' => 0, 'legacy' => 0, 'kept' => 0, 'ids' => []];
        $rows = DB::table($table)->where('channel_id', $channelId)->get(['id', 'external_id']);
        $final = [];

        foreach ($rows as $row) {
            $old = (string) $row->external_id;

            if (str_starts_with($old, self::LEGACY) || str_starts_with($old, 'novo:')) {
                $stats['kept']++;

                continue;
            }

            if (isset($map[$old])) {
                $final[$row->id] = (string) $map[$old];
                $stats['ids'][] = (int) $row->id;
                $stats['mapped']++;
            } elseif (in_array($old, array_map('strval', $map), true)) {
                $stats['kept']++; // já é um id da loja nova (rodada repetida)
            } else {
                $final[$row->id] = self::LEGACY.$old;
                $stats['legacy']++;
            }
        }

        foreach ($final as $id => $new) {
            DB::table($table)->where('id', $id)->update(['external_id' => 'novo:'.$new]);
        }

        foreach ($final as $id => $new) {
            DB::table($table)->where('id', $id)->update(['external_id' => $new]);
        }

        return $stats;
    }

    /**
     * Item de pedido sem SKU guarda o id do produto no campo de SKU externo;
     * esse id também muda.
     *
     * @return array{mapped:int, legacy:int, kept:int}
     */
    private function remapItems(array $orderIds, array $map): array
    {
        $stats = ['mapped' => 0, 'legacy' => 0, 'kept' => 0];

        foreach (array_chunk($orderIds, 200) as $chunk) {
            foreach (DB::table('order_items')->whereIn('order_id', $chunk)->get(['id', 'external_sku']) as $item) {
                $old = (string) $item->external_sku;

                if (isset($map[$old])) {
                    DB::table('order_items')->where('id', $item->id)->update(['external_sku' => (string) $map[$old]]);
                    $stats['mapped']++;
                } else {
                    $stats['kept']++;
                }
            }
        }

        return $stats;
    }
}
