<?php

namespace App\Notifications;

use App\Editorial\Import\Ai\AiAccountProblem;
use App\Filament\Resources\Imports\ImportResource;
use App\Models\Import;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-mail quando uma importação com IA para por falta de saldo ou chave recusada. */
class ImportBlocked extends Notification
{
    use Queueable;

    public function __construct(
        public Import $import,
        public string $kind,
        public string $instruction,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $problem = $this->kind === AiAccountProblem::CREDIT ? 'acabou o saldo da IA' : 'a chave da IA foi recusada';

        return (new MailMessage)
            ->subject("[Hub Editora] Importação parada: {$problem}")
            ->greeting('Olá!')
            ->line("A importação do PDF **{$this->import->original_name}** parou porque {$problem}.")
            ->line($this->instruction)
            ->action('Abrir a importação', ImportResource::getUrl('review', ['record' => $this->import]))
            ->line('Você recebe este aviso no máximo uma vez a cada '.AiAccountProblem::THROTTLE_HOURS.' horas para o mesmo problema.')
            ->salutation('Hub Editora');
    }
}
