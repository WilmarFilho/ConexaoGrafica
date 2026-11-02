<?php

namespace App\Editorial;

/**
 * Licenças Creative Commons dos direitos autorais (livro e capítulo). O código é o
 * que fica gravado; o texto explica ao leitor o que pode fazer com a obra e vai no
 * site e na ficha; a URL canônica vai no XML da Crossref (`ai:license_ref`).
 * Um livro pode ter mais de uma; capítulo sem licença própria herda as do livro.
 */
final class License
{
    /** @var array<string, array{name:string, title:string, url:string, elements:list<string>, text:string}> */
    public const ALL = [
        'cc-by' => [
            'name' => 'CC BY 4.0',
            'title' => 'Atribuição 4.0 Internacional',
            'url' => 'https://creativecommons.org/licenses/by/4.0/',
            'elements' => ['by'],
            'text' => 'Você tem a liberdade de compartilhar (copiar e redistribuir o material em qualquer suporte ou formato) e adaptar (remixar, transformar e criar a partir do material) para qualquer fim, mesmo comercial, desde que atribua o devido crédito ao autor original.',
        ],
        'cc-by-sa' => [
            'name' => 'CC BY-SA 4.0',
            'title' => 'Atribuição-CompartilhaIgual 4.0 Internacional',
            'url' => 'https://creativecommons.org/licenses/by-sa/4.0/',
            'elements' => ['by', 'sa'],
            'text' => 'Você tem a liberdade de compartilhar (copiar e redistribuir o material em qualquer suporte ou formato) e adaptar (remixar, transformar e criar a partir do material) para qualquer fim, mesmo comercial, desde que atribua o devido crédito e distribua suas contribuições sob a mesma licença do original.',
        ],
        'cc-by-nd' => [
            'name' => 'CC BY-ND 4.0',
            'title' => 'Atribuição-SemDerivações 4.0 Internacional',
            'url' => 'https://creativecommons.org/licenses/by-nd/4.0/',
            'elements' => ['by', 'nd'],
            'text' => 'Você tem a liberdade de compartilhar (copiar e redistribuir o material em qualquer suporte ou formato) para qualquer fim, mesmo comercial, desde que atribua o devido crédito. Se você remixar, transformar ou criar a partir do material, não poderá distribuir o material modificado.',
        ],
        'cc-by-nc' => [
            'name' => 'CC BY-NC 4.0',
            'title' => 'Atribuição-NãoComercial 4.0 Internacional',
            'url' => 'https://creativecommons.org/licenses/by-nc/4.0/',
            'elements' => ['by', 'nc'],
            'text' => 'Você tem a liberdade de compartilhar (copiar e redistribuir o material em qualquer suporte ou formato) e adaptar (remixar, transformar e criar a partir do material), desde que atribua o devido crédito. Você não pode usar o material para fins comerciais.',
        ],
        'cc-by-nc-sa' => [
            'name' => 'CC BY-NC-SA 4.0',
            'title' => 'Atribuição-NãoComercial-CompartilhaIgual 4.0 Internacional',
            'url' => 'https://creativecommons.org/licenses/by-nc-sa/4.0/',
            'elements' => ['by', 'nc', 'sa'],
            'text' => 'Você tem a liberdade de compartilhar (copiar e redistribuir o material em qualquer suporte ou formato) e adaptar (remixar, transformar e criar a partir do material), desde que atribua o devido crédito, não utilize o material para fins comerciais e distribua suas contribuições sob a mesma licença do original.',
        ],
        'cc-by-nc-nd' => [
            'name' => 'CC BY-NC-ND 4.0',
            'title' => 'Atribuição-NãoComercial-SemDerivações 4.0 Internacional',
            'url' => 'https://creativecommons.org/licenses/by-nc-nd/4.0/',
            'elements' => ['by', 'nc', 'nd'],
            'text' => 'Você tem a liberdade de compartilhar (copiar e redistribuir o material em qualquer suporte ou formato), desde que atribua o devido crédito. Você não pode usar o material para fins comerciais e, se remixar, transformar ou criar a partir do material, não poderá distribuir o material modificado.',
        ],
        'cc0' => [
            'name' => 'CC0 1.0',
            'title' => 'Dedicação ao Domínio Público 1.0 Universal',
            'url' => 'https://creativecommons.org/publicdomain/zero/1.0/',
            'elements' => ['zero'],
            'text' => 'O autor renunciou a todos os direitos autorais e direitos conexos sobre a obra, dedicando-a ao domínio público global. Você pode copiar, modificar e distribuir a obra, mesmo para fins comerciais, sem pedir permissão.',
        ],
    ];

    /** Opções para os formulários: código → "CC BY-NC 4.0 · Atribuição-NãoComercial…". */
    public static function options(): array
    {
        return array_map(fn (array $l) => $l['name'].' · '.$l['title'], self::ALL);
    }

    /** Texto de cada opção, para a descrição sob a caixa de seleção. */
    public static function descriptions(): array
    {
        return array_map(fn (array $l) => $l['text'], self::ALL);
    }

    /**
     * Só códigos conhecidos, sem repetir, na ordem do catálogo.
     *
     * @return list<string>
     */
    public static function normalize(mixed $codes): array
    {
        $codes = array_map(fn ($c) => strtolower(trim((string) $c)), (array) $codes);

        return array_values(array_filter(array_keys(self::ALL), fn ($k) => in_array($k, $codes, true)));
    }

    /**
     * Licenças completas para o site e a ficha. `$subject` entra na frase de abertura
     * ("Este livro está licenciado sob…", "Este capítulo está licenciado sob…").
     *
     * @return list<array{code:string, name:string, title:string, url:string, elements:list<string>, text:string, statement:string}>
     */
    public static function expand(array $codes, string $subject = 'Esta obra está licenciada'): array
    {
        $out = [];
        foreach (self::normalize($codes) as $code) {
            $l = self::ALL[$code];
            $statement = $code === 'cc0'
                ? $l['text']
                : "{$subject} sob uma Licença Creative Commons {$l['title']} ({$l['name']}). {$l['text']}";
            $out[] = ['code' => $code] + $l + ['statement' => $statement];
        }

        return $out;
    }

    /** @return list<string> URLs canônicas, para o XML da Crossref. */
    public static function urls(array $codes): array
    {
        return array_map(fn ($code) => self::ALL[$code]['url'], self::normalize($codes));
    }
}
