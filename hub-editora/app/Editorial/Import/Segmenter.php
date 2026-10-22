<?php

namespace App\Editorial\Import;

use Illuminate\Support\Str;

/**
 * Cruza o sumário lido pela IA com o texto extraído página a página e mapeia cada
 * capítulo a um intervalo de páginas do PDF e às páginas impressas (primeira/última).
 * Determinístico, sem IA: a IA só forneceu o sumário.
 *
 * Como funciona:
 *  1. Descobre o deslocamento "página do PDF − página impressa" mais frequente
 *     entre as páginas em que o número impresso foi lido (as páginas-âncora).
 *  2. Para cada capítulo do sumário, procura o título normalizado numa janela em
 *     torno da página esperada; não achando, procura no livro todo (fora das páginas
 *     que parecem sumário); não achando ainda, usa a página esperada com aviso.
 *  3. O capítulo vai até a página anterior ao início do seguinte; o último vai até
 *     o fim dos capítulos informado pela IA ou até a última página com texto.
 */
class Segmenter
{
    private const WINDOW = 3;

    /**
     * @param  list<array{pdf_page:int, printed_page:?int, text:string}>  $pages
     * @param  list<array{sequence:int, number:?string, title:string, section:?string, printed_page:?int}>  $toc  capítulos na ordem do sumário
     * @param  int|null  $endPrinted  primeira página impressa depois do último capítulo (índice, posfácio), se o sumário trouxer
     * @return array{offset:?int, anchors:int, chapters:list<array<string,mixed>>}
     */
    public function segment(array $pages, array $toc, ?int $endPrinted = null): array
    {
        $byPdf = [];
        foreach ($pages as $p) {
            $byPdf[$p['pdf_page']] = $p;
        }
        $total = count($pages);
        $offset = $this->offset($pages);
        $anchors = $offset === null ? 0 : count(array_filter($pages, fn ($p) => $p['printed_page'] !== null && $p['pdf_page'] - $p['printed_page'] === $offset));

        $normalized = array_map(fn ($p) => $this->normalize($p['text']), $byPdf);
        $keys = array_map(fn ($c) => $this->titleKey($c['title']), $toc);
        $tocPages = $this->tocPages($normalized, $keys);

        $chapters = [];
        $previousStart = 0;
        foreach ($toc as $i => $entry) {
            $key = $keys[$i];
            $expected = ($offset !== null && ! empty($entry['printed_page'])) ? $entry['printed_page'] + $offset : null;
            $found = null;
            $confidence = 0.0;

            if ($expected !== null) {
                $found = $this->searchWindow($normalized, $key, max(1, $expected - self::WINDOW), min($total, $expected + self::WINDOW), $previousStart);
                $confidence = $found !== null ? ($found === $expected ? 0.95 : 0.85) : 0.0;
            }
            if ($found === null && $key !== '') {
                $found = $this->searchAll($normalized, $key, $previousStart, $tocPages);
                $confidence = $found !== null ? ($expected === null ? 0.7 : 0.6) : 0.0;
            }
            if ($found === null && $expected !== null && $expected > $previousStart && $expected <= $total) {
                $found = $expected;
                $confidence = 0.4;
            }

            $needsReview = $found === null || $confidence < 0.6;
            $chapters[] = [
                'sequence' => $entry['sequence'],
                'number' => $entry['number'] ?? null,
                'section' => $entry['section'] ?? null,
                'title' => $entry['title'],
                'toc_printed_page' => $entry['printed_page'] ?? null,
                'pdf_first_page' => $found,
                'pdf_last_page' => null,
                'first_page' => null,
                'last_page' => null,
                'confidence' => round($confidence, 2),
                'needs_review' => $needsReview,
                'evidence' => $found !== null ? ['pdf_page' => $found, 'excerpt' => $this->excerpt($byPdf[$found]['text'] ?? '', $entry['title'])] : null,
            ];
            if ($found !== null) {
                $previousStart = $found;
            }
        }

        // Fim de cada capítulo: véspera do início do seguinte (pulando os não localizados).
        $lastText = $this->lastPageWithText($pages);
        $endPdf = ($offset !== null && $endPrinted) ? min($total, max(1, $endPrinted + $offset - 1)) : $lastText;
        $n = count($chapters);
        for ($i = 0; $i < $n; $i++) {
            if ($chapters[$i]['pdf_first_page'] === null) {
                continue;
            }
            $next = null;
            for ($j = $i + 1; $j < $n; $j++) {
                if ($chapters[$j]['pdf_first_page'] !== null) {
                    $next = $chapters[$j]['pdf_first_page'];
                    break;
                }
            }
            $end = $next !== null ? max($chapters[$i]['pdf_first_page'], $next - 1) : max($chapters[$i]['pdf_first_page'], $endPdf);
            $chapters[$i]['pdf_last_page'] = $end;

            $first = $chapters[$i]['toc_printed_page']
                ?? $byPdf[$chapters[$i]['pdf_first_page']]['printed_page']
                ?? ($offset !== null ? $chapters[$i]['pdf_first_page'] - $offset : null);
            $last = $byPdf[$end]['printed_page']
                ?? ($first !== null ? $first + ($end - $chapters[$i]['pdf_first_page']) : null);
            if ($first !== null && $last !== null && $last < $first) {
                $last = $first + ($end - $chapters[$i]['pdf_first_page']);
            }
            $chapters[$i]['first_page'] = $first;
            $chapters[$i]['last_page'] = $last;
            if ($first === null || $last === null) {
                $chapters[$i]['needs_review'] = true;
            }
        }

        return ['offset' => $offset, 'anchors' => $anchors, 'chapters' => $chapters];
    }

    /**
     * Texto de um intervalo de páginas com marcador de página no início de cada uma,
     * para o modelo citar a página certa na evidência.
     *
     * @param  list<array{pdf_page:int, printed_page:?int, text:string}>  $pages
     */
    public static function text(array $pages, int $from, int $to): string
    {
        $out = [];
        foreach ($pages as $p) {
            if ($p['pdf_page'] >= $from && $p['pdf_page'] <= $to) {
                $printed = $p['printed_page'] !== null ? "p. {$p['printed_page']}" : 'p. ?';
                $out[] = "[[{$printed} | PDF {$p['pdf_page']}]]\n".$p['text'];
            }
        }

        return implode("\n\n", $out);
    }

    /** Deslocamento "PDF − impressa" mais frequente; empate → o menor. */
    public function offset(array $pages): ?int
    {
        $counts = [];
        foreach ($pages as $p) {
            if ($p['printed_page'] !== null) {
                $d = $p['pdf_page'] - $p['printed_page'];
                $counts[$d] = ($counts[$d] ?? 0) + 1;
            }
        }
        if ($counts === []) {
            return null;
        }
        $max = max($counts);
        $best = array_keys(array_filter($counts, fn ($c) => $c === $max));
        sort($best);

        return $best[0];
    }

    /** Minúsculas, sem acento e sem pontuação: casa títulos quebrados em linhas ou em caixa alta. */
    public function normalize(string $text): string
    {
        $t = mb_strtolower(Str::ascii($text));
        $t = preg_replace('/[^a-z0-9]+/', ' ', $t) ?? $t;

        return ' '.trim($t).' ';
    }

    private function titleKey(string $title): string
    {
        $t = trim($this->normalize($title));
        // Tira "capitulo 3" / "3." do começo: no corpo o número costuma vir separado.
        $t = preg_replace('/^(capitulo|cap|chapter)?\s*\d+\s*/', '', $t) ?? $t;
        if (mb_strlen($t) > 60) {
            $t = mb_substr($t, 0, 60);
            $t = mb_substr($t, 0, (int) mb_strrpos($t, ' ')) ?: $t;
        }

        return mb_strlen($t) >= 6 ? ' '.trim($t).' ' : '';
    }

    /** Páginas em que aparecem vários títulos de capítulos distintos: é sumário, não início de capítulo. */
    private function tocPages(array $normalized, array $keys): array
    {
        $toc = [];
        $keys = array_filter($keys);
        $threshold = count($keys) <= 4 ? 2 : 3;
        foreach ($normalized as $pdf => $text) {
            $hits = 0;
            foreach ($keys as $k) {
                if (str_contains($text, $k) && ++$hits >= $threshold) {
                    $toc[$pdf] = true;
                    break;
                }
            }
        }

        return $toc;
    }

    private function searchWindow(array $normalized, string $key, int $from, int $to, int $after): ?int
    {
        if ($key === '') {
            return null;
        }
        $best = null;
        $bestPos = PHP_INT_MAX;
        for ($p = max($from, $after + 1); $p <= $to; $p++) {
            $pos = isset($normalized[$p]) ? strpos($normalized[$p], $key) : false;
            // Título mais perto do topo da página ganha (início de capítulo, não citação).
            if ($pos !== false && $pos < $bestPos) {
                $best = $p;
                $bestPos = $pos;
            }
        }

        return $best;
    }

    private function searchAll(array $normalized, string $key, int $after, array $tocPages): ?int
    {
        foreach ($normalized as $p => $text) {
            if ($p <= $after || isset($tocPages[$p])) {
                continue;
            }
            if (str_contains($text, $key)) {
                return $p;
            }
        }

        return null;
    }

    private function lastPageWithText(array $pages): int
    {
        $last = count($pages);
        for ($i = count($pages) - 1; $i >= 0; $i--) {
            if (mb_strlen(trim($pages[$i]['text'])) > 20) {
                return $pages[$i]['pdf_page'];
            }
        }

        return $last;
    }

    private function excerpt(string $text, string $title): string
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text))));
        $norm = $this->normalize($title);
        foreach ($lines as $i => $line) {
            $window = implode(' ', array_slice($lines, $i, 3));
            if (str_contains($this->normalize($window), trim($norm)) || str_contains($norm, trim($this->normalize($line)))) {
                return mb_substr($window, 0, 160);
            }
        }

        return mb_substr(implode(' ', array_slice($lines, 0, 3)), 0, 160);
    }
}
