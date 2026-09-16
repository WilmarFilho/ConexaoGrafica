<?php

namespace App\Notifications;

use App\Filament\Resources\SyncLogs\SyncLogResource;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Resumo quinzenal da auditoria para a revisão periódica exigida pela Amazon. */
class SecurityDigest extends Notification
{
    /** @param array<string, int|string> $stats */
    public function __construct(public string $period, public array $stats, public array $highlights) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = (new MailMessage)
            ->subject("[Hub Editora] Revisão de segurança · {$this->period}")
            ->greeting('Revisão quinzenal')
            ->line("Período: {$this->period}. Confira os números e abra a Auditoria se algo não bater com a rotina.");

        foreach ($this->stats as $label => $value) {
            $m->line("• {$label}: {$value}");
        }

        if ($this->highlights) {
            $m->line('Pontos de atenção:');
            foreach ($this->highlights as $h) {
                $m->line("⚠ {$h}");
            }
        } else {
            $m->line('Nenhum ponto de atenção no período.');
        }

        return $m->action('Abrir a Auditoria', SyncLogResource::getUrl())->salutation('Hub Editora');
    }
}
