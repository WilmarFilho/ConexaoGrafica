<?php

namespace App\Console\Commands;

use App\Crossref\CrossrefException;
use App\Crossref\DepositService;
use App\Models\Deposit;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;

/**
 * Lê o relatório de processamento da Crossref e atualiza os DOIs do depósito.
 *
 *   php artisan hub:crossref-resultado 12            um depósito (id ou batch_id)
 *   php artisan hub:crossref-resultado --pendentes   todos em sent/processing (para o agendador)
 *
 * Agendamento sugerido em routes/console.php:
 *   Schedule::command('hub:crossref-resultado --pendentes')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
 */
class CrossrefResultCommand extends Command
{
    protected $signature = 'hub:crossref-resultado {deposit? : id ou batch_id do depósito} {--pendentes : Consulta todos os depósitos aguardando resultado}';

    protected $description = 'Consulta o resultado do processamento de depósitos na Crossref';

    public function handle(): int
    {
        $key = $this->argument('deposit');

        if ($this->option('pendentes')) {
            $deposits = Deposit::pendingResult()->orderBy('submitted_at')->get();
        } elseif ($key !== null) {
            $deposit = ctype_digit((string) $key) ? Deposit::find((int) $key) : Deposit::where('batch_id', $key)->first();
            if (! $deposit) {
                $this->error('Depósito não encontrado.');

                return self::FAILURE;
            }
            $deposits = collect([$deposit]);
        } else {
            $this->error('Informe o depósito ou use --pendentes.');

            return self::FAILURE;
        }

        if ($deposits->isEmpty()) {
            $this->info('Nenhum depósito aguardando resultado.');

            return self::SUCCESS;
        }

        $service = DepositService::make();
        $failures = 0;

        foreach ($deposits as $deposit) {
            try {
                $deposit = $service->refresh($deposit);
            } catch (ConnectionException|CrossrefException $e) {
                $this->error("{$deposit->batch_id}: {$e->getMessage()}");
                $failures++;

                continue;
            }

            $this->line(sprintf('%s: %s', $deposit->batch_id, $deposit->status_label));
            foreach ($deposit->items as $item) {
                $this->line(sprintf('  %-45s %-11s %s', $item->doi, $item->status_label, (string) $item->message));
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
