<?php
/**
 * Filtros e ordenação do catálogo. Tudo por GET, para o estado do catálogo
 * caber num link e funcionar sem JavaScript.
 */

if (! defined('ABSPATH')) {
    exit;
}

/** Grupos de filtro por taxonomia, na ordem do layout. */
function conexao_filtros_taxonomia(): array
{
    return apply_filters('conexao_filtros_taxonomia', [
        'cat' => ['taxonomia' => 'product_cat', 'titulo' => 'Categorias'],
        'colecao' => ['taxonomia' => 'colecao', 'titulo' => 'Coleções'],
        'autor' => ['taxonomia' => 'autor', 'titulo' => 'Autores', 'busca' => true],
        'formato' => ['taxonomia' => 'pa_formato', 'titulo' => 'Formato'],
    ]);
}

/** Valores escolhidos num filtro, já limpos. */
function conexao_filtro_selecionado(string $chave): array
{
    $bruto = $_GET[$chave] ?? []; // phpcs:ignore WordPress.Security.NonceVerification

    if (! is_array($bruto)) {
        $bruto = explode(',', (string) $bruto);
    }

    return array_values(array_filter(array_map('sanitize_title', wp_unslash($bruto))));
}

/** Faixa de preço do catálogo inteiro, para as pontas do controle. */
function conexao_faixa_precos(): array
{
    $faixa = get_transient('conexao_faixa_precos');

    if (is_array($faixa)) {
        return $faixa;
    }

    global $wpdb;

    $maximo = (float) $wpdb->get_var(
        "SELECT MAX(max_price) FROM {$wpdb->wc_product_meta_lookup}"
    );

    $faixa = ['min' => 0, 'max' => max(100, (int) ceil($maximo / 50) * 50)];
    set_transient('conexao_faixa_precos', $faixa, HOUR_IN_SECONDS);

    return $faixa;
}

/**
 * Aplica os filtros na consulta da loja. Preço fica com o WooCommerce, que já
 * entende min_price/max_price.
 */
add_action('pre_get_posts', function (WP_Query $query): void {
    if (is_admin() || ! $query->is_main_query()) {
        return;
    }

    if (! (function_exists('is_shop') && (is_shop() || is_product_taxonomy()))) {
        return;
    }

    // 15 livros por página: 5 colunas por 3 linhas, como no layout
    $query->set('posts_per_page', 15);

    $tax_query = (array) $query->get('tax_query');

    foreach (conexao_filtros_taxonomia() as $chave => $grupo) {
        $termos = conexao_filtro_selecionado($chave);

        if (! $termos || ! taxonomy_exists($grupo['taxonomia'])) {
            continue;
        }

        $tax_query[] = [
            'taxonomy' => $grupo['taxonomia'],
            'field' => 'slug',
            'terms' => $termos,
            'operator' => 'IN',
        ];
    }

    if (count($tax_query) > 1) {
        $tax_query['relation'] = 'AND';
    }

    if ($tax_query) {
        $query->set('tax_query', $tax_query);
    }

    $estoque = conexao_filtro_selecionado('estoque');

    if ($estoque) {
        $meta_query = (array) $query->get('meta_query');
        $meta_query[] = [
            'key' => '_stock_status',
            'value' => array_intersect($estoque, ['instock', 'onbackorder', 'outofstock']),
            'compare' => 'IN',
        ];
        $query->set('meta_query', $meta_query);
    }
});

/** Opções do seletor de ordenação, com os rótulos do layout. */
function conexao_ordenacoes(): array
{
    return [
        'date' => 'Lançamentos: Mais recentes',
        'popularity' => 'Mais vendidos',
        'price' => 'Preço: menor para maior',
        'price-desc' => 'Preço: maior para menor',
        'title' => 'Título: A a Z',
    ];
}

/** "Pág. 1-15 de 37 resultados", como no layout. */
function conexao_resumo_resultados(): string
{
    global $wp_query;

    $total = (int) $wp_query->found_posts;

    if (! $total) {
        return '';
    }

    $por_pagina = (int) $wp_query->get('posts_per_page');
    $pagina = max(1, (int) $wp_query->get('paged'));
    $primeiro = ($pagina - 1) * $por_pagina + 1;
    $ultimo = min($total, $pagina * $por_pagina);

    return sprintf(
        'Pág. %d-%d de %d %s',
        $primeiro,
        $ultimo,
        $total,
        $total === 1 ? 'resultado' : 'resultados'
    );
}

/** Link mantendo os filtros atuais e trocando só um parâmetro. */
function conexao_link_catalogo(array $trocas): string
{
    $atual = $_GET; // phpcs:ignore WordPress.Security.NonceVerification
    unset($atual['paged']);

    foreach ($trocas as $chave => $valor) {
        if ($valor === null) {
            unset($atual[$chave]);
            continue;
        }

        $atual[$chave] = $valor;
    }

    $base = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/loja/');

    return $atual ? add_query_arg(array_map('rawurlencode_deep', $atual), $base) : $base;
}
