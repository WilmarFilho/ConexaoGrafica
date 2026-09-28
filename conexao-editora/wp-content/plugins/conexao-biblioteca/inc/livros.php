<?php
/**
 * O livro digital de cada produto. Dois modelos convivem:
 *  - pdf: o modelo novo; o PDF é o arquivo mestre e o leitor o desenha;
 *  - flipbook: o legado da Pubcon, mantido até a editora enviar o PDF.
 * Quando o produto tem os dois, vale o PDF.
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_LIVRO_PDF = '_conexao_livro_pdf';             // caminho relativo dentro de pdf/
const CONEXAO_LIVRO_PDF_DADOS = '_conexao_livro_pdf_dados'; // nome original, tamanho, data
const CONEXAO_LIVRO_FLIPBOOK = '_conexao_livro_flipbook';   // nome da pasta dentro de flipbooks/
const CONEXAO_LIVRO_COMBO = '_conexao_livro_combo';         // produtos liberados por este (coleções)

/** O produto "dono" do livro: a variação responde pelo produto pai. */
function conexao_biblioteca_produto_base(int $produto_id): int
{
    $pai = (int) wp_get_post_parent_id($produto_id);

    return ($pai && get_post_type($produto_id) === 'product_variation') ? $pai : $produto_id;
}

/**
 * @return array{modelo: string, pdf: string, flipbook: string, pagina: string}
 *         modelo é 'pdf', 'flipbook' ou '' (ainda sem arquivo)
 */
function conexao_biblioteca_livro(int $produto_id): array
{
    $produto_id = conexao_biblioteca_produto_base($produto_id);
    $livro = ['modelo' => '', 'pdf' => '', 'flipbook' => '', 'pagina' => ''];

    $pdf = (string) get_post_meta($produto_id, CONEXAO_LIVRO_PDF, true);

    if ($pdf !== '') {
        $caminho = conexao_biblioteca_caminho_seguro(conexao_biblioteca_subpasta('pdf'), $pdf);

        if ($caminho) {
            $livro['pdf'] = $caminho;
        }
    }

    $pasta = (string) get_post_meta($produto_id, CONEXAO_LIVRO_FLIPBOOK, true);

    if ($pasta !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $pasta)) {
        $raiz = conexao_biblioteca_subpasta('flipbooks').'/'.$pasta;

        if (is_dir($raiz)) {
            $livro['flipbook'] = $raiz;
            $livro['pagina'] = conexao_biblioteca_pagina_flipbook($raiz);
        }
    }

    if ($livro['pdf']) {
        $livro['modelo'] = 'pdf';
    } elseif ($livro['flipbook'] && $livro['pagina']) {
        $livro['modelo'] = 'flipbook';
    }

    return $livro;
}

/** A página de abertura do flipbook: o .html da raiz que não seja índice. */
function conexao_biblioteca_pagina_flipbook(string $raiz): string
{
    $candidatas = glob($raiz.'/*.html') ?: [];

    foreach ($candidatas as $arquivo) {
        $nome = basename($arquivo);

        if (! in_array(strtolower($nome), ['index.html', 'index.htm'], true)) {
            return $nome;
        }
    }

    return $candidatas ? basename($candidatas[0]) : '';
}

/** O produto entrega leitura digital? (simples virtual, ou variável com formato digital) */
function conexao_biblioteca_e_digital(int $produto_id): bool
{
    $produto_id = conexao_biblioteca_produto_base($produto_id);

    if (get_post_meta($produto_id, CONEXAO_LIVRO_PDF, true) || get_post_meta($produto_id, CONEXAO_LIVRO_FLIPBOOK, true)) {
        return true;
    }

    $produto = function_exists('wc_get_product') ? wc_get_product($produto_id) : null;

    if (! $produto) {
        return false;
    }

    if ($produto->is_type('variable')) {
        foreach ($produto->get_children() as $filha) {
            if (conexao_biblioteca_variacao_digital((int) $filha)) {
                return true;
            }
        }

        return false;
    }

    return $produto->is_virtual() || $produto->is_downloadable();
}

/** A variação dá direito à leitura? Vale para "E-book" e para o combo. */
function conexao_biblioteca_variacao_digital(int $variacao_id): bool
{
    $formato = (string) get_post_meta($variacao_id, 'attribute_pa_formato', true);
    $digitais = (array) apply_filters('conexao_biblioteca_formatos_digitais', ['e-book', 'impresso-e-book']);

    if ($formato !== '') {
        return in_array($formato, $digitais, true);
    }

    return get_post_meta($variacao_id, '_virtual', true) === 'yes'
        || get_post_meta($variacao_id, '_downloadable', true) === 'yes';
}

/** Produtos liberados por uma coleção (a Série CBO libera os 15 volumes). */
function conexao_biblioteca_combo(int $produto_id): array
{
    $itens = get_post_meta(conexao_biblioteca_produto_base($produto_id), CONEXAO_LIVRO_COMBO, true);

    return array_values(array_filter(array_map('intval', (array) $itens)));
}

/** Todos os produtos com leitura digital, para as telas do painel. */
function conexao_biblioteca_todos_os_livros(): array
{
    $ids = get_posts([
        'post_type' => 'product',
        'post_status' => ['publish', 'draft', 'private'],
        'numberposts' => -1,
        'fields' => 'ids',
        'orderby' => 'title',
        'order' => 'ASC',
    ]);

    return array_values(array_filter($ids, static function (int $id): bool {
        // o e-book antigo que virou formato de outro produto não conta duas vezes
        if (get_post_meta($id, '_conexao_unificado_em', true)) {
            return false;
        }

        return conexao_biblioteca_e_digital($id);
    }));
}
