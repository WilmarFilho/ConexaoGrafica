<?php

namespace App\Console\Commands;

use App\Crossref\CrossrefException;
use App\Crossref\DepositService;
use App\Models\Deposit;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;

/**
 * Envia um depósito gravado (queued ou failed sem relatório) à Crossref, agora,
 * sem passar pela fila.
 *
 *   php artisan hub:crossref-enviar 12
 */
class CrossrefSendCommand extends Command
{
    protected $signature = 'hub:crossref-enviar {deposit : id ou batch_id do depósito}';

    protected $description = 'Envia um depósito à Crossref (test.crossref.org ou doi.crossref.org conforme CROSSREF_ENV)';

    public function handle(): int
    {
        $deposit = $this->find((string) $this->argument('deposit'));
        if (! $deposit) {
            $this->error('Depósito não encontrado.');

            return self::FAILURE;
        }

        try {
            $deposit = DepositService::make()->send($deposit);
        } catch (ConnectionException $e) {
            $this->error('Sem resposta da Crossref: '.$e->getMessage());

            return self::FAILURE;
        } catch (CrossrefException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($deposit->status !== Deposit::SENT) {
            $this->error("Depósito {$deposit->batch_id} recusado (HTTP {$deposit->http_status}): ".mb_substr((string) $deposit->response, 0, 300));

            return self::FAILURE;
        }

        $this->info("Depósito {$deposit->batch_id} recebido pela Crossref ({$deposit->environment}). Consulte o resultado com hub:crossref-resultado {$deposit->id}.");

        return self::SUCCESS;
    }

    private function find(string $key): ?Deposit
    {
        return ctype_digit($key) ? Deposit::find((int) $key) : Deposit::where('batch_id', $key)->first();
    }
}
