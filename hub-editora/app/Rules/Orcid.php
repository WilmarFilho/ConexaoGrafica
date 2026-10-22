<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * ORCID iD: 16 caracteres (15 dígitos + verificador 0-9 ou X) no formato
 * 0000-0000-0000-000X, com checksum ISO 7064 MOD 11-2. Aceita a URL
 * https://orcid.org/… e devolve sempre a forma com hífens.
 */
class Orcid implements ValidationRule
{
    /** Forma canônica 0000-0000-0000-000X (ou o texto limpo, se não tiver o tamanho certo). */
    public static function normalize(?string $value): ?string
    {
        $v = trim((string) $value);

        if ($v === '') {
            return null;
        }

        $v = preg_replace('~^https?://(www\.)?orcid\.org/~i', '', $v) ?? $v;
        $v = strtoupper(preg_replace('/[\s\-]+/', '', $v) ?? '');

        return preg_match('/^\d{15}[\dX]$/', $v) ? implode('-', str_split($v, 4)) : $v;
    }

    public static function isValid(?string $value): bool
    {
        $digits = str_replace('-', '', (string) self::normalize($value));

        if (! preg_match('/^\d{15}[\dX]$/', $digits)) {
            return false;
        }

        $total = 0;
        for ($i = 0; $i < 15; $i++) {
            $total = ($total + (int) $digits[$i]) * 2;
        }
        $result = (12 - $total % 11) % 11;

        return ($result === 10 ? 'X' : (string) $result) === $digits[15];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (filled($value) && ! self::isValid((string) $value)) {
            $fail('ORCID inválido: use o formato 0000-0000-0000-000X (o último caractere é o verificador).');
        }
    }
}
