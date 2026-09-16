<?php

namespace App\Notifications;

use App\Filament\Resources\SyncLogs\SyncLogResource;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SecurityAlert extends Notification
{
    public function __construct(public string $message) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[Hub Editora] Alerta de segurança')
            ->greeting('Atenção')
            ->line($this->message)
            ->line('Se não reconhece essa atividade, troque sua senha e revise os usuários do painel.')
            ->action('Abrir a Auditoria', SyncLogResource::getUrl())
            ->salutation('Hub Editora');
    }
}
