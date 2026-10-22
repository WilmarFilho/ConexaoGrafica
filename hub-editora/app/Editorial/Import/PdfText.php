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
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
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
