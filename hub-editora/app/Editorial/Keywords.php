<?php

namespace App\Editorial;

/**
 * Palavras-chave sempre como lista de textos. Formulários e importações às vezes
 * entregam "a, b; c" ou "" (o que quebrava a tela do livro ao contar os itens).
 */
class Keywords
{
    /** @return list<string> */
    public static function normalize(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[,;\n]+/', $value) ?: [];
        }
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $v) {
            $v = is_scalar($v) ? trim((string) $v) : '';
            if ($v !== '' && ! in_array($v, $out, true)) {
                $out[] = $v;
            }
        }

        return $out;
    }
}
