<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

#[Fillable(['name', 'email', 'password', 'password_changed_at', 'role'])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Administra usuários, integrações e auditoria. */
    public const ROLE_ADMIN = 'admin';

    /** Só pedidos, expedição e catálogo: o mínimo para separar e enviar. */
    public const ROLE_EXPEDICAO = 'expedicao';

    public const ROLES = [self::ROLE_ADMIN => 'Administrador', self::ROLE_EXPEDICAO => 'Expedição'];

    /** Senhas valem por um ano; depois o painel exige troca. */
    public const PASSWORD_MAX_AGE_DAYS = 365;

    /** Troca voluntária de senha só depois de 1 dia da anterior. */
    public const PASSWORD_MIN_AGE_HOURS = 24;

    /** Quantas senhas anteriores não podem ser repetidas. */
    public const PASSWORD_HISTORY = 10;

    /** Tentativas falhas seguidas que bloqueiam a conta, e por quanto tempo. */
    public const LOCK_AFTER_FAILURES = 10;

    public const LOCK_MINUTES = 30;

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->isDirty('password')) {
                // Troca de senha (reset, perfil, criação) zera o relógio da expiração e desbloqueia.
                if (! $user->isDirty('password_changed_at')) {
                    $user->password_changed_at = now();
                }
                if ($user->exists) {
                    $user->failed_logins = 0;
                    $user->locked_until = null;
                }
            }
        });

        // Guarda o hash anterior para não deixar repetir as últimas senhas.
        static::updating(function (User $user) {
            $old = $user->getOriginal('password');

            if ($user->isDirty('password') && $old) {
                PasswordHistory::create(['user_id' => $user->id, 'password' => $old, 'created_at' => now()]);

                $keep = PasswordHistory::where('user_id', $user->id)->latest('id')->limit(self::PASSWORD_HISTORY)->pluck('id');
                PasswordHistory::where('user_id', $user->id)->whereNotIn('id', $keep)->delete();
            }
        });
    }

    /**
     * Fora do ambiente local o Filament exige esta resposta explícita, senão
     * devolve 403 para todo mundo. Não há cadastro aberto: só entra quem foi
     * criado por um administrador, não está desativado nem bloqueado.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->disabled_at === null && ! $this->isLocked();
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    /** @param Builder<User> $query */
    public function scopeAdmins(Builder $query): void
    {
        $query->where('role', self::ROLE_ADMIN)->whereNull('disabled_at');
    }

    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistory::class);
    }

    public function passwordIsExpired(): bool
    {
        return $this->password_changed_at !== null
            && $this->password_changed_at->lt(now()->subDays(self::PASSWORD_MAX_AGE_DAYS));
    }

    /** A senha em texto já foi usada (atual ou uma das últimas)? */
    public function passwordWasUsed(string $plain): bool
    {
        if ($this->password && Hash::check($plain, $this->password)) {
            return true;
        }

        return $this->passwordHistories()->latest('id')->limit(self::PASSWORD_HISTORY)->pluck('password')
            ->contains(fn (string $hash) => Hash::check($plain, $hash));
    }

    /** Troca voluntária liberada (senha atual com mais de 1 dia ou já vencida)? */
    public function canChangePasswordNow(): bool
    {
        return $this->password_changed_at === null
            || $this->passwordIsExpired()
            || $this->password_changed_at->lte(now()->subHours(self::PASSWORD_MIN_AGE_HOURS));
    }

    // ---- Autenticação em dois fatores (app autenticador) ----------------

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'locked_until' => 'datetime',
            'disabled_at' => 'datetime',
            'last_login_at' => 'datetime',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }
}
