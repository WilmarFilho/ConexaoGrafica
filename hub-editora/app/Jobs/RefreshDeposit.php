<?php

namespace App\Jobs;

use App\Crossref\BookDeposits;
use App\Crossref\DepositService;
use App\Models\Deposit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Lê o relatório de processamento de um depósito e atualiza os DOIs. Se a
 * Crossref ainda não terminou, agenda outra leitura (até esgotar as tentativas);
 * depois disso, a varredura periódica `hub:crossref-resultado --pendentes` assume.
 */
class RefreshDeposit implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Minutos de espera entre leituras enquanto o lote está em processamento. */
    public const RETRY_MINUTES = 10;

    public function __construct(public int $depositId, public int $attempt = 1) {}

    public function handle(): void
    {
        $deposit = Deposit::query()->find($this->depositId);

        if (! $deposit || ! $deposit->awaitingResult()) {
            return;
        }

        $deposit = DepositService::make()->refresh($deposit);

        // Espelha o resultado (processing, registered ou failed) no livro e nos capítulos (frente E1).
        BookDeposits::make()->sync($deposit);

        if ($deposit->awaitingResult() && $this->attempt < 6) {
            self::dispatch($deposit->id, $this->attempt + 1)->delay(now()->addMinutes(self::RETRY_MINUTES));
        }
    }
}
