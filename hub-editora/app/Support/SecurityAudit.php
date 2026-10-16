<?php

namespace App\Support;

use App\Models\SyncLog;
use App\Models\User;
use App\Notifications\SecurityAlert;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Registro de autenticação na Auditoria, alerta de força bruta e bloqueio de conta.
 * - Cinco falhas em 15 minutos para o mesmo e-mail ou IP avisam os administradores.
 * - Dez falhas seguidas na mesma conta bloqueiam a conta por 30 minutos (ou até
 *   um administrador desbloquear / a pessoa redefinir a senha pelo link).
 */
class SecurityAudit
{
    public const FAILED_THRESHOLD = 5;

    public const FAILED_WINDOW_MINUTES = 15;

    public static function login(?Authenticatable $user, Request $request): void
    {
        if ($user instanceof User) {
            $user->forceFill(['failed_logins' => 0, 'locked_until' => null, 'last_login_at' => now()])->saveQuietly();
        }

        SyncLog::record(null, SyncLog::MANUAL, 'auth.login', $user instanceof User ? $user : null,
            'Login de '.($user->email ?? '?').' · IP '.$request->ip(), ['ip' => $request->ip(), 'agent' => mb_substr((string) $request->userAgent(), 0, 160)]);
    }

    public static function logout(?Authenticatable $user, Request $request): void
    {
        SyncLog::record(null, SyncLog::MANUAL, 'auth.logout', $user instanceof User ? $user : null,
            'Saída de '.($user->email ?? '?'), ['ip' => $request->ip()]);
    }

    public static function failed(?string $email, Request $request): void
    {
        $email = mb_strtolower((string) $email);
        $ip = (string) $request->ip();

        SyncLog::record(null, SyncLog::MANUAL, 'auth.failed', null,
            "Tentativa de login falhou para {$email} · IP {$ip}", ['ip' => $ip, 'email' => $email], 'warning');

        self::countTowardsLock($email, $ip);

        $hits = [];
        foreach (['e-mail '.$email => 'email:'.$email, 'IP '.$ip => 'ip:'.$ip] as $label => $key) {
            $count = (int) Cache::get("hub.auth.failed.{$key}", 0) + 1;
            Cache::put("hub.auth.failed.{$key}", $count, now()->addMinutes(self::FAILED_WINDOW_MINUTES));

            if ($count === self::FAILED_THRESHOLD) {
                $hits[] = $label;
            }
        }

        // Um aviso só por rajada, mesmo que e-mail e IP estourem juntos.
        if ($hits) {
            self::alert(self::FAILED_THRESHOLD.' tentativas de login falhas em '.self::FAILED_WINDOW_MINUTES.' minutos ('.implode(' e ', $hits).').');
        }
    }

    /** Conta a falha na conta do e-mail (se existir) e bloqueia na décima seguida. */
    private static function countTowardsLock(string $email, string $ip): void
    {
        $user = $email !== '' ? User::where('email', $email)->first() : null;

        if (! $user || $user->isLocked()) {
            return; // e-mail inexistente ou já bloqueada: não estende o bloqueio
        }

        $failures = ($user->locked_until !== null ? 0 : $user->failed_logins) + 1;
        $user->forceFill(['failed_logins' => $failures, 'locked_until' => null])->saveQuietly();

        if ($failures >= User::LOCK_AFTER_FAILURES) {
            $user->forceFill(['locked_until' => now()->addMinutes(User::LOCK_MINUTES)])->saveQuietly();
            SyncLog::record(null, SyncLog::MANUAL, 'auth.locked', $user,
                "Conta {$email} bloqueada por ".User::LOCK_MINUTES." minutos após {$failures} tentativas falhas · IP {$ip}", ['ip' => $ip], 'warning');
            self::alert("A conta {$email} foi bloqueada após {$failures} tentativas de login falhas (último IP {$ip}). Ela libera sozinha em ".User::LOCK_MINUTES.' minutos; um administrador pode desbloquear antes na tela Usuários.');
        }
    }

    /** E-mail aos administradores; usado também para outros eventos sensíveis. */
    public static function alert(string $message): void
    {
        foreach (User::query()->admins()->whereNotNull('email')->get() as $user) {
            try {
                $user->notify(new SecurityAlert($message));
            } catch (Throwable) {
                // e-mail fora do ar não pode quebrar o login
            }
        }

        SyncLog::record(null, SyncLog::MANUAL, 'alert.sent', null, 'Alerta de segurança: '.$message, [], 'warning');
    }
}
