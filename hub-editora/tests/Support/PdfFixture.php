<?php

namespace Tests\Support;

/**
 * Gera um PDF mínimo (1.4, sem compressão, Helvetica/WinAnsi) com camada de texto,
 * para testar a extração sem depender de arquivo binário no repositório.
 *
 * Livro de exemplo (6 páginas): capa, ficha catalográfica + autores, sumário,
 * capítulo 1 (págs. impressas 9–10) e capítulo 2 (pág. impressa 11). As três
 * primeiras páginas não têm número impresso, como num livro de verdade.
 */
class PdfFixture
{
    public const TITLE = 'Tratado de Doencas Raras';

    public const CH1 = 'Uma breve historia das doencas raras';

    public const CH2 = 'Genetica nas doencas raras';

    /** @return list<array{lines:list<string>, number:?int}> */
    public static function bookPages(): array
    {
        return [
            ['number' => null, 'lines' => [
                self::TITLE,
                'Diagnostico, manejo e politicas publicas',
                'Organizadores: Maria Exemplo da Silva e Joao Exemplo Pereira',
                'Conexao Editora - Goiania - 2026',
            ]],
            ['number' => null, 'lines' => [
                'Ficha catalografica',
                'ISBN 978-65-00000-00-9 (impresso)',
                'ISBN 978-65-00000-01-6 (e-book)',
                'Autores: Ana Exemplo Rocha - Universidade Federal de Goias',
                'Carlos Exemplo Lima - Hospital das Clinicas da UFG',
                '1. Doencas raras. 2. Genetica medica. CDU 616',
            ]],
            ['number' => null, 'lines' => [
                'Sumario',
                'Secao 1 - Fundamentos',
                'Capitulo 1 - '.self::CH1.' ........ 9',
                'Capitulo 2 - '.self::CH2.' ........ 11',
                'Indice remissivo ........ 13',
            ]],
            ['number' => 9, 'lines' => [
                'Capitulo 1',
                self::CH1,
                'Ana Exemplo Rocha, Maria Exemplo da Silva',
                'Resumo: Este capitulo apresenta a evolucao do conceito de doenca rara,',
                'os marcos regulatorios internacionais e brasileiros e o papel das associacoes.',
                'Palavras-chave: doencas raras; historia da medicina; legislacao.',
                'O conceito de doenca rara surgiu na decada de 1980 nos Estados Unidos.',
            ]],
            ['number' => 10, 'lines' => [
                'No Brasil, a Portaria 199 de 2014 instituiu a Politica Nacional de Atencao Integral.',
                'Referencias',
                'BRASIL. Ministerio da Saude. Portaria n. 199, de 30 de janeiro de 2014.',
            ]],
            ['number' => 11, 'lines' => [
                'Capitulo 2',
                self::CH2,
                'Carlos Exemplo Lima',
                'As doencas raras sao, em sua maioria, de origem genetica. Este capitulo revisa',
                'os mecanismos moleculares, os metodos de diagnostico e o aconselhamento genetico.',
                'O sequenciamento de nova geracao ampliou o diagnostico em cerca de 40% dos casos.',
            ]],
        ];
    }

    /** Grava o livro de exemplo num arquivo temporário e devolve o caminho. */
    public static function write(?string $path = null): string
    {
        $path ??= sys_get_temp_dir().'/hub-fixture-'.uniqid().'.pdf';
        file_put_contents($path, self::build(self::bookPages()));

        return $path;
    }

    /**
     * Monta o PDF: cada página é um conjunto de linhas (11 pt, de cima para baixo) e,
     * opcionalmente, um número impresso centralizado no rodapé.
     *
     * @param  list<array{lines:list<string>, number:?int}>  $pages
     */
    public static function build(array $pages): string
    {
        $n = count($pages);
        $objects = [];
        $kids = [];
        for ($i = 0; $i < $n; $i++) {
            $kids[] = (4 + 2 * $i).' 0 R';
        }
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids)."] /Count {$n} >>";
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';

        foreach ($pages as $i => $page) {
            $pageNo = 4 + 2 * $i;
            $contentNo = 5 + 2 * $i;
            $stream = "BT\n/F1 11 Tf\n14 TL\n72 760 Td\n";
            foreach ($page['lines'] as $line) {
                $stream .= '('.self::escape($line).") Tj\nT*\n";
            }
            $stream .= "ET\n";
            if (! empty($page['number'])) {
                $stream .= "BT\n/F1 10 Tf\n297 40 Td\n(".$page['number'].") Tj\nET\n";
            }
            $objects[$pageNo] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentNo} 0 R >>";
            $objects[$contentNo] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}endstream";
        }

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        $count = count($objects);
        for ($i = 1; $i <= $count; $i++) {
            $offsets[$i] = strlen($pdf);
            $pdf .= "{$i} 0 obj\n{$objects[$i]}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".($count + 1)."\n0000000000 65535 f \n";
        for ($i = 1; $i <= $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= 'trailer
<< /Size '.($count + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        return $pdf;
    }

    private static function escape(string $text): string
    {
        $text = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');

        return strtr($text, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => ' ', "\n" => ' ']);
    }
}
