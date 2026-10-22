<?php
/**
 * A página do livro (DOI) passa a ser a página de venda: produto da loja ligado a
 * uma publicação aponta para ela em todos os links (vitrine, busca, carrinho) e o
 * endereço antigo do produto redireciona (301). Produtos sem publicação ficam como
 * estão. O vínculo vem do Hub (commercial[].woo_product_id) e fica na meta
 * _conexao_woo_produto da publicação, um valor por produto.
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_DOI_META_WOO = '_conexao_woo_produto';

/** Grava os produtos da loja ligados ao livro (chamado a cada PUT do Hub). */
function conexao_doi_indexar_produtos(int $livro_id, array $livro): void
{
    delete_post_meta($livro_id, CONEXAO_DOI_META_WOO);
    foreach ((array) ($livro['commercial'] ?? []) as $link) {
        $id = (int) ($link['woo_product_id'] ?? 0);
        if ($id > 0 && ! empty($link['enabled'])) {
            add_post_meta($livro_id, CONEXAO_DOI_META_WOO, $id);
        }
    }
}

/** Publicação (livro no ar) ligada a um produto ou à variação dele; 0 se não houver. */
function conexao_doi_publicacao_do_produto(int $produto_id): int
{
    static $cache = [];
    if ($produto_id <= 0) {
        return 0;
    }
    if (array_key_exists($produto_id, $cache)) {
        return $cache[$produto_id];
    }

    $ids = [$produto_id];
    $pai = (int) wp_get_post_parent_id($produto_id);
    if ($pai > 0 && get_post_type($produto_id) === 'product_variation') {
        $ids[] = $pai;
    }

    $livros = get_posts([
        'post_type' => 'publicacao',
        'post_status' => 'publish',
        'numberposts' => 1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_query' => [
            ['key' => CONEXAO_DOI_META_WOO, 'value' => $ids, 'compare' => 'IN', 'type' => 'NUMERIC'],
            ['key' => CONEXAO_DOI_META_STATUS, 'value' => 'unpublished', 'compare' => '!='],
        ],
    ]);

    return $cache[$produto_id] = (int) ($livros[0] ?? 0);
}

/** Links de produto (vitrine, busca, carrinho, e-mails) apontam para a página do livro. */
add_filter('post_type_link', function (string $link, WP_Post $post): string {
    if (! in_array($post->post_type, ['product', 'product_variation'], true) || is_admin()) {
        return $link;
    }
    $livro = conexao_doi_publicacao_do_produto($post->ID);

    return $livro ? (string) get_permalink($livro) : $link;
}, 20, 2);

/** Endereço antigo do produto redireciona para o livro (301), preservando a query (utm etc.). */
add_action('template_redirect', function (): void {
    if (! function_exists('is_product') || ! is_product()) {
        return;
    }
    $livro = conexao_doi_publicacao_do_produto((int) get_queried_object_id());
    if (! $livro) {
        return;
    }
    $destino = (string) get_permalink($livro);
    if (! empty($_SERVER['QUERY_STRING'])) {
        $destino .= (str_contains($destino, '?') ? '&' : '?').sanitize_text_field(wp_unslash($_SERVER['QUERY_STRING']));
    }
    wp_safe_redirect($destino, 301, 'Conexao DOI');
    exit;
}, 5);

/** Uma vez por versão: indexa as publicações gravadas antes desta função existir. */
add_action('init', function (): void {
    if (get_option('conexao_doi_indice_loja') === CONEXAO_DOI_VERSAO) {
        return;
    }
    foreach (get_posts(['post_type' => 'publicacao', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids']) as $id) {
        $dados = json_decode((string) get_post_meta($id, CONEXAO_DOI_META_DADOS, true), true);
        if (is_array($dados['book'] ?? null)) {
            conexao_doi_indexar_produtos((int) $id, $dados['book']);
        }
    }
    update_option('conexao_doi_indice_loja', CONEXAO_DOI_VERSAO, false);
}, 30);
