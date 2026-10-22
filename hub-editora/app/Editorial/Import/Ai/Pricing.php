<?php

namespace App\Editorial\Import\Ai;

/**
 * Custo estimado em US$ a partir do uso de tokens e da tabela de preços em
 * config('hub.ai.prices') (US$ por milhão de tokens: input, cached_input, output).
 * Tokens de entrada em cache custam o preço `cached_input`; os de raciocínio já
 * estão dentro de output_tokens. Modelo sem preço cadastrado custa 0 e fica
 * marcado em usage.price_known = false.
 */
class Pricing
{
    public static function cost(string $model, array $usage): float
    {
        $p = self::prices($model);
        if ($p === null) {
            return 0.0;
        }

        $cached = min((int) ($usage['cached_input_tokens'] ?? 0), (int) ($usage['input_tokens'] ?? 0));
        $cost = (($usage['input_tokens'] ?? 0) - $cached) * $p['input']
            + $cached * ($p['cached_input'] ?? $p['input'])
            + ($usage['output_tokens'] ?? 0) * $p['output'];

        return round($cost / 1_000_000, 6);
    }

    public static function known(string $model): bool
    {
        return self::prices($model) !== null;
    }

    /** @return array{input:float, cached_input?:float, output:float}|null */
    private static function prices(string $model): ?array
    {
        $table = (array) config('hub.ai.prices', []);
        if (isset($table[$model])) {
            return $table[$model];
        }
        // "gpt-6-astra-2026-09-01" ou alias com data: casa pelo prefixo.
        foreach ($table as $id => $prices) {
            if (str_starts_with($model, $id)) {
                return $prices;
            }
        }

        return null;
    }
}
