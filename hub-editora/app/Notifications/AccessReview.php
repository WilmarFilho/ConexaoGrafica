<?php

namespace App\Notifications;

use App\Filament\Resources\Users\UserResource;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Revisão trimestral de acessos: quem entra no painel, com que perfil, desde quando sem usar. */
class AccessReview extends Notification
{
    /**
     * @param  array<int, array<string, string>>  $users
     * @param  array<int, string>  $disabled  e-mails desativados por inatividade nesta revisão
     */
    public function __construct(public array $users, public array $disabled) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = (new MailMessage)
            ->subject('[Hub Editora] Revisão trimestral de acessos')
            ->greeting('Revisão de acessos')
            ->line('Confira se cada pessoa abaixo ainda precisa de acesso e se o perfil está certo. Quem saiu da equipe ou mudou de função deve ser desativado em até 24 horas.');

        foreach ($this->users as $u) {
            $m->line("• {$u['nome']} ({$u['email']}) · {$u['perfil']} · {$u['situacao']} · 2 fatores: {$u['dois_fatores']} · último acesso: {$u['ultimo_acesso']}");
        }

        if ($this->disabled) {
            $m->line('Desativados agora por mais de 90 dias sem acesso: '.implode(', ', $this->disabled).'.');
        }

        return $m->line('Depois de conferir, registre a revisão na tela Usuários ("Registrar revisão de acessos").')
            ->action('Abrir Usuários', UserResource::getUrl())
            ->salutation('Hub Editora');
    }
}
