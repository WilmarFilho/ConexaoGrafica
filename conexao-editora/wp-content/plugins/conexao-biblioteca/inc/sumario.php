<?php
/**
 * Sumário do leitor montado com os capítulos que o Hub já publicou (seções,
 * número, título e página de início), sem digitar de novo.
 *
 * As páginas do Hub são as impressas; o PDF costuma ter páginas a mais antes
 * (capa, ficha, sumário). A diferença é achada no próprio PDF: o Ghostscript
 * extrai o texto das primeiras páginas e procura o título do capítulo 1, e a
 * conta só vale se o capítulo 2 também estiver onde ela diz. O resultado fica
 * guardado no produto e é refeito quando o PDF muda.
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_LIVRO_DESLOCAMENTO = '_conexao_livro_deslocamento'; // ['arquivo' => assinatura do PDF, 'valor' => int|null, 'manual' => bool]

/** Capítulos do livro no Hub (pela publicação do Conexão DOI ligada ao produto), na ordem. */
function conexao_biblioteca_capitulos_do_hub(int $produto_id): array
{
    if (! function_exists('conexao_doi_publicacao_do_produto') || ! function_exists('conexao_doi_dados_livro') || ! function_exists('conexao_doi_sumario')) {
        return [];
    }

    $publicacao = conexao_doi_publicacao_do_produto(conexao_biblioteca_produto_base($produto_id));

    if (! $publicacao) {
        return [];
    }

    $saida = [];

    foreach (conexao_doi_sumario(conexao_doi_dados_livro($publicacao)) as $secao) {
        foreach ($secao['capitulos'] as $capitulo) {
            if ((int) ($capitulo['first_page'] ?? 0) > 0 && trim((string) ($capitulo['title'] ?? '')) !== '') {
                $saida[] = [
                    'secao' => trim((string) $secao['section']),
                    'numero' => (string) (($capitulo['number'] ?? '') ?: ($capitulo['sequence'] ?? '')),
                    'titulo' => trim((string) $capitulo['title']),
                    'pagina' => (int) $capitulo['first_page'],
                ];
            }
        }
    }

    return $saida;
}

/**
 * Diferença entre a página do PDF e a impressa (null: não deu para achar).
 * Com $so_guardado, não roda o Ghostscript agora (a leitura não espera): agenda
 * o cálculo para logo depois e devolve null desta vez.
 */
function conexao_biblioteca_deslocamento(int $produto_id, bool $refazer = false, bool $so_guardado = false): ?int
{
    $base = conexao_biblioteca_produto_base($produto_id);
    $livro = conexao_biblioteca_livro($base);

    if (! $livro['pdf']) {
        return null;
    }

    $assinatura = @filemtime($livro['pdf']).'-'.@filesize($livro['pdf']);
    $guardado = get_post_meta($base, CONEXAO_LIVRO_DESLOCAMENTO, true);

    if (is_array($guardado) && ! empty($guardado['manual'])) {
        return (int) $guardado['valor'];
    }

    if (! $refazer && is_array($guardado) && ($guardado['arquivo'] ?? '') === $assinatura) {
        return $guardado['valor'] === null ? null : (int) $guardado['valor'];
    }

    $capitulos = conexao_biblioteca_capitulos_do_hub($base);

    if (! $capitulos) {
        return null;
    }

    if ($so_guardado) {
        if (! wp_next_scheduled('conexao_biblioteca_calcula_sumario', [$base])) {
            wp_schedule_single_event(time(), 'conexao_biblioteca_calcula_sumario', [$base]);
        }

        return null;
    }

    $valor = conexao_biblioteca_detecta_deslocamento($livro['pdf'], $capitulos);

    update_post_meta($base, CONEXAO_LIVRO_DESLOCAMENTO, ['arquivo' => $assinatura, 'valor' => $valor, 'manual' => false]);

    return $valor;
}

/** "Inteligência Artificial…" → "inteligencia artificial" (sem acento, pontuação nem espaço repetido). */
function conexao_biblioteca_texto_busca(string $texto): string
{
    $texto = strtolower(remove_accents($texto));
    $texto = (string) preg_replace('/[^a-z0-9]+/', ' ', $texto);

    return trim($texto);
}

/**
 * Procura a página do PDF onde começa o capítulo 1 e confere com o 2.
 *
 * @param list<array{titulo: string, pagina: int}> $capitulos
 */
function conexao_biblioteca_detecta_deslocamento(string $pdf, array $capitulos): ?int
{
    $gs = function_exists('conexao_biblioteca_gs') ? conexao_biblioteca_gs() : '';

    if ($gs === '' || ! $capitulos) {
        return null;
    }

    $primeiro = $capitulos[0];
    $segundo = $capitulos[1] ?? null;
    $ate = min(400, ($segundo['pagina'] ?? $primeiro['pagina']) + 80);
    $paginas = conexao_biblioteca_texto_das_paginas($gs, $pdf, $ate);

    if (! $paginas) {
        return null;
    }

    $chave = static function (string $titulo): string {
        // as primeiras palavras bastam e escapam de quebra de linha no meio do título
        $palavras = explode(' ', conexao_biblioteca_texto_busca($titulo));

        return implode(' ', array_slice($palavras, 0, 6));
    };

    $achou = static function (int $pagina, string $procurada) use ($paginas): bool {
        return $procurada !== '' && isset($paginas[$pagina]) && str_contains($paginas[$pagina], $procurada);
    };

    $chave1 = $chave($primeiro['titulo']);
    $chave2 = $segundo ? $chave($segundo['titulo']) : '';

    foreach ($paginas as $numero => $texto) {
        if (! str_contains($texto, $chave1)) {
            continue;
        }

        $deslocamento = $numero - $primeiro['pagina'];

        // a página do sumário também traz o título: só vale se o capítulo 2 bater
        if (! $segundo) {
            return $deslocamento >= 0 ? $deslocamento : null;
        }

        foreach ([0, -1, 1] as $folga) {
            if ($achou($segundo['pagina'] + $deslocamento + $folga, $chave2)) {
                return $deslocamento;
            }
        }
    }

    return null;
}

/** Texto de cada página (1..$ate) pelo Ghostscript, já normalizado: [página => texto]. */
function conexao_biblioteca_texto_das_paginas(string $gs, string $pdf, int $ate): array
{
    $pasta = trailingslashit(get_temp_dir()).'conexao-sumario-'.wp_generate_password(8, false);
    wp_mkdir_p($pasta);

    $processo = proc_open(
        [$gs, '-q', '-dSAFER', '-dBATCH', '-dNOPAUSE', '-sDEVICE=txtwrite', '-dFirstPage=1', '-dLastPage='.$ate, '-sOutputFile='.$pasta.'/p%04d.txt', $pdf],
        [['file', '/dev/null', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
        $tubos
    );

    if (is_resource($processo)) {
        proc_close($processo);
    }

    $paginas = [];

    foreach (glob($pasta.'/p*.txt') ?: [] as $arquivo) {
        $paginas[(int) substr(basename($arquivo, '.txt'), 1)] = conexao_biblioteca_texto_busca((string) file_get_contents($arquivo));
        @unlink($arquivo);
    }

    @rmdir($pasta);
    ksort($paginas);

    return $paginas;
}

/**
 * Sumário do Hub já em páginas do PDF: seções (nível 0) e capítulos (nível 1).
 *
 * @return list<array{titulo: string, pagina: int, nivel: int}>
 */
function conexao_biblioteca_sumario_do_hub(int $produto_id): array
{
    $capitulos = conexao_biblioteca_capitulos_do_hub($produto_id);

    if (! $capitulos) {
        return [];
    }

    $deslocamento = conexao_biblioteca_deslocamento($produto_id, false, true);

    if ($deslocamento === null) {
        return [];
    }

    $itens = [];
    $secao = null;
    $com_secoes = count(array_filter(array_column($capitulos, 'secao'))) > 0;

    foreach ($capitulos as $capitulo) {
        $pagina = $capitulo['pagina'] + $deslocamento;

        if ($com_secoes && $capitulo['secao'] !== $secao) {
            $secao = $capitulo['secao'];

            if ($secao !== '') {
                $itens[] = ['titulo' => $secao, 'pagina' => $pagina, 'nivel' => 0];
            }
        }

        $titulo = ($capitulo['numero'] !== '' ? 'Capítulo '.$capitulo['numero'].' · ' : '').conexao_biblioteca_titulo_legivel($capitulo['titulo']);
        $itens[] = ['titulo' => $titulo, 'pagina' => $pagina, 'nivel' => $com_secoes ? 1 : 0];
    }

    return $itens;
}

/** Título todo em maiúsculas (como vem de muitos livros) vira "Primeira maiúscula". */
function conexao_biblioteca_titulo_legivel(string $titulo): string
{
    if ($titulo !== mb_strtoupper($titulo)) {
        return $titulo;
    }

    $minusculo = mb_strtolower($titulo);

    return mb_strtoupper(mb_substr($minusculo, 0, 1)).mb_substr($minusculo, 1);
}

// cálculo agendado pela leitura (não atrasa a abertura do livro)
add_action('conexao_biblioteca_calcula_sumario', function (int $produto_id): void {
    conexao_biblioteca_deslocamento($produto_id, true);
});

// produto salvo (PDF novo, capítulos novos): já deixa calculado
add_action('save_post_product', function (int $post_id): void {
    if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }

    if (conexao_biblioteca_livro($post_id)['pdf'] && conexao_biblioteca_capitulos_do_hub($post_id)) {
        conexao_biblioteca_deslocamento($post_id);
    }
}, 30);
