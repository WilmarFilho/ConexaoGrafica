<?php

namespace App\Console\Commands;

use App\Integrations\PagarMe\ImportOrders as PagarMeImport;
use App\Integrations\WooCommerce\ImportOrders as WooImport;
use Illuminate\Console\Command;

/**
 * php artisan hub:sync woocommerce            (varredura dos últimos N dias)
 * php artisan hub:sync woocommerce --days=90  (carga inicial / reprocessamento)
 *
 * Varredura de segurança: roda pelo agendador a cada 15 min e pega o que o
 * webhook eventualmente perdeu.
 */
class SyncOrders extends Command
{
    protected $signature = 'hub:sync {channel : woocommerce | pagarme | amazon} {--days= : Quantos dias para trás (padrão: configuração do canal)}';

    protected $description = 'Importa/atualiza pedidos de um canal para o hub';

    public function handle(): int
    {
        $channel = $this->argument('channel');
        $days = $this->option('days') !== null ? max(1, (int) $this->option('days')) : null;

        // Canal sem credencial: avisa e sai, sem virar erro no log a cada 15 min.
        $configured = match ($channel) {
            'woocommerce' => filled(config('hub.woocommerce.url')) && filled(config('hub.woocommerce.key')),
            'pagarme' => filled(config('hub.pagarme.secret_key')),
            default => false,
        };

        if (! $configured) {
            $this->warn("{$channel}: canal não configurado no .env; nada a fazer.");

            return self::SUCCESS;
        }

        $count = match ($channel) {
            'woocommerce' => WooImport::make()->sinceLookback($days),
            'pagarme' => PagarMeImport::make()->sinceLookback($days ?? 3),
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
