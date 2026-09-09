<?php

namespace App\Console\Commands;

use App\Integrations\WooCommerce\ImportProducts as WooProducts;
use Illuminate\Console\Command;

/**
 * php artisan hub:sync-products woocommerce
 *
 * Catálogo → tabela de produtos (peso, medidas, estoque, e-book ou físico).
 * Roda uma vez por dia pelo agendador; pode rodar à mão após cadastrar títulos.
 */
class SyncProducts extends Command
{
    protected $signature = 'hub:sync-products {channel : woocommerce}';

    protected $description = 'Importa/atualiza o catálogo de um canal para a tabela de produtos';

    public function handle(): int
    {
        $channel = $this->argument('channel');

        if ($channel !== 'woocommerce') {
            $this->error("Canal ainda não implementado: {$channel}");

            return self::FAILURE;
        }

        if (! filled(config('hub.woocommerce.url')) || ! filled(config('hub.woocommerce.key'))) {
            $this->warn('woocommerce: canal não configurado no .env; nada a fazer.');

            return self::SUCCESS;
        }

        $r = WooProducts::make()->all();
        $this->info("woocommerce: {$r['products']} produto(s) sincronizado(s), {$r['relinked']} item(ns) de pedido religado(s).");

        return self::SUCCESS;
    }
}
