<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * ISBN-13: 13 dígitos começando por 978 ou 979, com dígito verificador
 * (pesos 1 e 3 alternados, módulo 10). Hífens e espaços são ignorados.
 */
class Isbn13 implements ValidationRule
{
    /** Só os dígitos. */
    public static function normalize(?string $value): string
    {
        return preg_replace('/[\s\-]+/', '', (string) $value) ?? '';
    }

    public static function isValid(?string $value): bool
    {
        $digits = self::normalize($value);

        if (! preg_match('/^97[89]\d{10}$/', $digits)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $digits[$i] * ($i % 2 ? 3 : 1);
        }

        return (10 - $sum % 10) % 10 === (int) $digits[12];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (filled($value) && ! self::isValid((string) $value)) {
            $fail('ISBN-13 inválido: confira os 13 dígitos e o dígito verificador.');
        }
    }
}
