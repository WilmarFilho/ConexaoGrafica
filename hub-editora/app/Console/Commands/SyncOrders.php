<?php

namespace App\Console\Commands;

use App\Integrations\PagarMe\ImportOrders as PagarMeImport;
use App\Integrations\WooCommerce\ImportOrders as WooImport;
use Illuminate\Console\Command;

/**
 * php artisan hub:sync woocommerce
 *
 * Varredura de segurança: roda pelo agendador a cada 15 min e pega o que o
 * webhook eventualmente perdeu.
 */
class SyncOrders extends Command
{
    protected $signature = 'hub:sync {channel : woocommerce | pagarme | amazon}';

    protected $description = 'Importa/atualiza pedidos de um canal para o hub';

    public function handle(): int
    {
        $channel = $this->argument('channel');

        $count = match ($channel) {
            'woocommerce' => WooImport::make()->sinceLookback(),
            'pagarme' => PagarMeImport::make()->sinceLookback(),
            default => null,
        };

        if ($count === null) {
            $this->error("Canal ainda não implementado: {$channel}");

            return self::FAILURE;
        }

        $this->info("{$channel}: {$count} pedido(s) processado(s).");

        return self::SUCCESS;
    }
}
