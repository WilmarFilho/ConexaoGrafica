<?php

namespace App\Console\Commands;

use App\Models\SyncLog;
use App\Models\User;
use App\Notifications\AccessReview;
use Illuminate\Console\Command;

/**
 * Revisão trimestral de acessos (Amazon DPP 1.2): envia aos administradores a
 * lista de quem tem acesso, com perfil e último login, e desativa contas de
 * Expedição sem uso há mais de 90 dias. O administrador confirma a revisão
 * na tela Usuários ("Registrar revisão de acessos").
 */
class AccessReviewCommand extends Command
{
    public const INACTIVE_DAYS = 90;

    protected $signature = 'hub:access-review';

    protected $description = 'Envia a revisão trimestral de acessos aos administradores e desativa contas sem uso';

    public function handle(): int
    {
        $limite = now()->subDays(self::INACTIVE_DAYS);
        $desativados = [];

        $inativos = User::query()
            ->where('role', User::ROLE_EXPEDICAO)
            ->whereNull('disabled_at')
            ->where('created_at', '<=', $limite)
            ->where(fn ($q) => $q->whereNull('last_login_at')->orWhere('last_login_at', '<=', $limite))
            ->get();

        foreach ($inativos as $user) {
            $user->forceFill(['disabled_at' => now()])->saveQuietly();
            SyncLog::record(null, SyncLog::MANUAL, 'user.disabled', $user, 'Usuário '.$user->email.' desativado por '.self::INACTIVE_DAYS.' dias sem acesso (revisão trimestral)');
            $desativados[] = $user->email;
        }

        $linhas = User::query()->orderBy('name')->get()->map(fn (User $u) => [
            'nome' => $u->name,
            'email' => $u->email,
            'perfil' => $u->roleLabel(),
            'situacao' => $u->disabled_at ? 'desativado' : ($u->isLocked() ? 'bloqueado' : 'ativo'),
            'dois_fatores' => filled($u->app_authentication_secret) ? 'sim' : 'não',
            'ultimo_acesso' => $u->last_login_at?->format('d/m/Y') ?? 'nunca',
        ])->all();

        $admins = User::query()->admins()->whereNotNull('email')->get();
        foreach ($admins as $admin) {
            $admin->notify(new AccessReview($linhas, $desativados));
        }

        SyncLog::record(null, SyncLog::OUT, 'access.review.sent', null,
            'Revisão de acessos enviada a '.$admins->count().' administrador(es): '.count($linhas).' conta(s), '.count($desativados).' desativada(s) por inatividade',
            ['desativados' => $desativados]);

        $this->info('Revisão enviada; '.count($desativados).' conta(s) desativada(s) por inatividade.');

        return self::SUCCESS;
    }
}
