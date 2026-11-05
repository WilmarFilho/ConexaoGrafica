<?php

namespace App\Editorial\Import;

use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;

/**
 * Texto de um PDF com camada de texto, página a página, com tentativa de ler o
 * número impresso de cada página (cabeçalho ou rodapé). Sem OCR: PDF digitalizado
 * devolve páginas vazias e a importação avisa.
 *
 * Saída de extract(): lista de ['pdf_page' => 1, 'printed_page' => 21|null, 'text' => '...'].
 */
class PdfText
{
    /** Tamanho máximo de um stream descomprimido (protege de PDF malicioso). */
    private const DECODE_LIMIT = 64 * 1024 * 1024;

    /** @return list<array{pdf_page:int, printed_page:?int, text:string}> */
    public function extract(string $path): array
    {
        if (! is_file($path)) {
            throw new \RuntimeException("Arquivo não encontrado: {$path}");
        }

        $config = new Config;
        $config->setRetainImageContent(false);
        $config->setDecodeMemoryLimit(self::DECODE_LIMIT);

        $document = (new Parser([], $config))->parseFile($path);

        $pages = [];
        foreach ($document->getPages() as $i => $page) {
            try {
                $text = $this->clean((string) $page->getText());
            } catch (\Throwable) {
                $text = '';
            }
            $pages[] = [
                'pdf_page' => $i + 1,
                'printed_page' => $this->printedPage($text),
                'text' => $text,
            ];
        }

        return $pages;
    }

    /** Quantas páginas têm texto de verdade (acima de um punhado de caracteres). */
    public static function pagesWithText(array $pages): int
    {
        return count(array_filter($pages, fn ($p) => mb_strlen(trim($p['text'])) > 20));
    }

    /** Normaliza quebras e espaços sem perder a divisão em linhas (os cabeçalhos dependem dela). */
    private function clean(string $text): string
    {
        $text = self::utf8($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Garante UTF-8 válido. Fonte com codificação própria às vezes sai como byte solto
     * do Windows-1252 (0x8C = "Œ" de "Œsophageal" numa referência do Tratado de Doenças
     * Raras), e um byte inválido derruba o json_encode da chamada à IA e do `result`.
     * Sequências UTF-8 válidas ficam; cada byte solto é lido como Windows-1252.
     */
    public static function utf8(string $text): string
    {
        if (mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }

        $valid = '[\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}'
            .'|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}';

        return preg_replace_callback("/{$valid}|(.)/s", function (array $m): string {
            if (! isset($m[1])) {
                return $m[0];
            }
            $char = @mb_convert_encoding($m[1], 'UTF-8', 'Windows-1252');

            return is_string($char) && $char !== '' && mb_check_encoding($char, 'UTF-8') ? $char : '?';
        }, $text) ?? mb_scrub($text, 'UTF-8');
    }

    /**
     * Número impresso: um inteiro de 1 a 4 dígitos sozinho (ou ladeado de traços,
     * barras ou pontos) nas primeiras ou últimas linhas, ou começando/terminando
     * uma linha curta de cabeçalho ("23 Capítulo 1", "Título | 23").
     */
    public function printedPage(string $text): ?int
    {
        if ($text === '') {
            return null;
        }

        $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), fn ($l) => $l !== ''));
        if ($lines === []) {
            return null;
        }

        $candidates = array_unique(array_merge(
            array_slice($lines, 0, 2),
            array_slice($lines, -2),
        ));

        // 1ª passada: linha só com o número ("23", "— 23 —", "- 23 -", "| 23 |", "23.").
        foreach ($candidates as $line) {
            if (preg_match('/^[\s\-–—|•.·]*(\d{1,4})[\s\-–—|•.·]*$/u', $line, $m)) {
                return $this->valid((int) $m[1]);
            }
        }

        // 2ª passada: número na ponta de uma linha curta de cabeçalho/rodapé
        // ("23 | Capítulo 1", "Título do capítulo 23"). Linha com cara de frase ou de
        // ficha catalográfica ("2. Genética. CDU 616") não conta.
        foreach ($candidates as $line) {
            if (mb_strlen($line) > 90 || preg_match('/[.;:,]\s|\d\.\s*\p{L}/u', $line)) {
                continue;
            }
            if (preg_match('/^(\d{1,4})\s*[|\-–—•·]?\s+\p{L}/u', $line, $m)
                || preg_match('/\p{L}\S*\s+[|\-–—•·]?\s*(\d{1,4})$/u', $line, $m)) {
                return $this->valid((int) $m[1]);
            }
        }

        return null;
    }

    private function valid(int $n): ?int
    {
        return ($n >= 1 && $n <= 2000) ? $n : null;
    }
}
