<?php

namespace App\Jobs;

use App\Crossref\DepositService;
use App\Models\Deposit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Envia um depósito à Crossref pela fila. Só falha de rede faz a fila repetir
 * (o DepositService já marcou `failed`; a nova tentativa reenvia). Depósito
 * aceito agenda a primeira leitura do resultado para daqui a alguns minutos.
 *
 * Agendamento sugerido (routes/console.php, não alterado por esta frente):
 *   Schedule::command('hub:crossref-resultado --pendentes')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
 */
class SendDeposit implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> segundos entre tentativas */
    public array $backoff = [60, 300];

    public function __construct(public int $depositId) {}

    public function handle(): void
    {
        $deposit = Deposit::query()->find($this->depositId);

        if (! $deposit || ! $deposit->canBeSent()) {
            return;
        }

        // Falha de rede lança ConnectionException: o depósito já está `failed` e a fila tenta de novo.
        $deposit = DepositService::make()->send($deposit);

        if ($deposit->status === Deposit::SENT) {
            RefreshDeposit::dispatch($deposit->id)->delay(now()->addMinutes(5));
        }
    }
}
