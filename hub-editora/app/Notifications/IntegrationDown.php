<?php

namespace App\Notifications;

use App\Filament\Pages\Integracoes;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-mail para os usuários do painel quando uma integração cai. */
class IntegrationDown extends Notification
{
    use Queueable;

    public function __construct(
        public string $channelName,
        public string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[Hub Editora] {$this->channelName} desconectou")
            ->greeting('Olá!')
            ->line("A integração **{$this->channelName}** parou de responder e o hub deixou de sincronizar esse canal.")
            ->line('Motivo informado: '.$this->reason)
            ->action('Abrir Integrações', Integracoes::getUrl())
            ->line('Na tela de Integrações dá para testar a conexão, trocar as chaves ou reconectar. Você recebe este aviso no máximo uma vez a cada 6 horas por canal.')
            ->salutation('Hub Editora');
    }
}
