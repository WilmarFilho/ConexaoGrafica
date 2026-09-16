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
 * Registro de autenticação na Auditoria e alerta de força bruta.
 * Cinco falhas em 15 minutos para o mesmo e-mail ou IP avisam os administradores.
 */
class SecurityAudit
{
    public const FAILED_THRESHOLD = 5;

    public const FAILED_WINDOW_MINUTES = 15;

    public static function login(?Authenticatable $user, Request $request): void
    {
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

        foreach (['email:'.$email, 'ip:'.$ip] as $key) {
            $count = (int) Cache::get("hub.auth.failed.{$key}", 0) + 1;
            Cache::put("hub.auth.failed.{$key}", $count, now()->addMinutes(self::FAILED_WINDOW_MINUTES));

            if ($count === self::FAILED_THRESHOLD) {
                self::alert("{$count} tentativas de login falhas em ".self::FAILED_WINDOW_MINUTES." minutos para {$key}. Última origem: IP {$ip}.");
            }
        }
    }

    /** E-mail a todos os usuários; usado também para outros eventos sensíveis. */
    public static function alert(string $message): void
    {
        foreach (User::query()->whereNotNull('email')->get() as $user) {
            try {
                $user->notify(new SecurityAlert($message));
            } catch (Throwable) {
                // e-mail fora do ar não pode quebrar o login
            }
        }

        SyncLog::record(null, SyncLog::MANUAL, 'alert.sent', null, 'Alerta de segurança: '.$message, [], 'warning');
    }
}
