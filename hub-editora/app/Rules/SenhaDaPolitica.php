<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Regras de senha que dependem do usuário (a parte genérica — 12+ caracteres,
 * maiúsculas, minúsculas, número, símbolo e vazamentos — é o Password::defaults):
 *  - não conter partes do nome nem do e-mail;
 *  - não repetir a senha atual nem as 10 anteriores;
 *  - troca voluntária só 1 dia depois da anterior (não vale para o link de recuperação).
 */
class SenhaDaPolitica implements ValidationRule
{
    public function __construct(
        private readonly ?User $user,
        private readonly bool $checkMinAge = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $plain = (string) $value;

        if (! $this->user || $plain === '') {
            return;
        }

        $senha = Str::lower(Str::ascii($plain));

        foreach ($this->identityParts() as $parte) {
            if (str_contains($senha, $parte)) {
                $fail('A senha não pode conter partes do seu nome ou do seu e-mail.');

                return;
            }
        }

        if ($this->user->passwordWasUsed($plain)) {
            $fail('Escolha uma senha diferente da atual e das '.User::PASSWORD_HISTORY.' anteriores.');

            return;
        }

        if ($this->checkMinAge && ! $this->user->canChangePasswordNow()) {
            $fail('A senha foi trocada há menos de '.User::PASSWORD_MIN_AGE_HOURS.' horas. Tente de novo depois desse prazo.');
        }
    }

    /** Pedaços do nome e do e-mail com 3 letras ou mais, sem acento e em minúsculas. */
    private function identityParts(): array
    {
        $local = Str::before((string) $this->user->email, '@');
        $texto = Str::lower(Str::ascii($this->user->name.' '.$local));
        $partes = preg_split('/[^a-z0-9]+/', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($partes, fn ($p) => mb_strlen($p) >= 3)));
    }
}
