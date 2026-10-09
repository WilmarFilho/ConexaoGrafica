<?php
/**
 * Categorias de livro em dois níveis: categoria principal (Medicina, História...)
 * e subcategoria (Oftalmologia, História de Goiás...). Cada livro fica nas duas.
 * As categorias da loja antiga (Livro, E-books, Livro da CBO...) deixam de ter
 * livros e os endereços delas levam, com 301, para o destino novo.
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A categoria mais específica do livro (a subcategoria, quando houver) e a
 * principal acima dela.
 *
 * @return array{0: ?WP_Term, 1: ?WP_Term} [subcategoria ou principal, principal]
 */
function conexao_categoria_do_livro(int $produto_id): array
{
    $termos = get_the_terms($produto_id, 'product_cat');

    if (is_wp_error($termos) || ! $termos) {
        return [null, null];
    }

    $padrao = (int) get_option('default_product_cat');
    $termos = array_values(array_filter($termos, static fn (WP_Term $t): bool => $t->term_id !== $padrao));

    if (! $termos) {
        return [null, null];
    }

    usort($termos, static fn (WP_Term $a, WP_Term $b): int => ($b->parent ? 1 : 0) <=> ($a->parent ? 1 : 0));
    $especifica = $termos[0];
    $principal = $especifica->parent ? get_term($especifica->parent, 'product_cat') : $especifica;

    return [$especifica, $principal instanceof WP_Term ? $principal : $especifica];
}

/**
 * Para onde vai cada categoria antiga: o slug da categoria nova ou, quando o
 * assunto era formato/coleção, o catálogo já filtrado.
 *
 * @return array<string, string|array<string, string[]>>
 */
function conexao_categorias_antigas(): array
{
    return [
        'e-book-biografia' => 'biografia',
        'livro-biografia' => 'biografia',
        'cleuza' => 'biografia',
        'livro-cronicas' => 'cronica',
        'e-book-dermatologia' => 'dermatologia',
        'livro-dermatologia' => 'dermatologia',
        'e-book-mastologia' => 'mastologia',
        'livro-mastologia' => 'mastologia',
        'e-book-oftalmologia' => 'oftalmologia',
        'livro-oftalmologia' => 'oftalmologia',
        'e-book-ultrassonografia' => 'ultrassonografia',
        'livro-ultrassonografia' => 'ultrassonografia',
        'ebook-educacao-familiar' => 'educacao-familiar',
        'livro-educacao-familiar' => 'educacao-familiar',
        'fertilidade' => 'ginecologia-e-obstetricia',
        'e-books-relatos' => 'historia-da-medicina-e-das-instituicoes',
        'livro-da-cbo' => ['colecao' => ['cbo']],
        'ebook-da-cbo' => ['colecao' => ['cbo']],
        'livro-da-sbus' => ['colecao' => ['sbus']],
        'ebook-sbus' => ['colecao' => ['sbus']],
        'e-books' => ['formato' => ['e-book']],
        'e-books02' => ['formato' => ['e-book']],
        'livro' => ['formato' => ['impresso']],
    ];
}

/** Endereço novo de uma categoria antiga, ou '' se ela não é antiga. */
function conexao_destino_categoria_antiga(string $slug): string
{
    $destino = conexao_categorias_antigas()[$slug] ?? null;

    if ($destino === null) {
        return '';
    }

    if (is_array($destino)) {
        return add_query_arg($destino, wc_get_page_permalink('shop'));
    }

    $termo = get_term_by('slug', $destino, 'product_cat');
    $link = $termo ? get_term_link($termo) : '';

    return is_wp_error($link) ? '' : (string) $link;
}

/*
 * 301 das categorias antigas. Só quando a antiga já não tem livros (depois da
 * reorganização), e também quando ela for apagada no painel (o endereço daria 404).
 */
add_action('template_redirect', function (): void {
    $slug = '';

    if (function_exists('is_product_category') && is_product_category()) {
        $termo = get_queried_object();

        if ($termo instanceof WP_Term && (int) $termo->count === 0) {
            $slug = $termo->slug;
        }
    } elseif (is_404()) {
        $base = trim((string) (wc_get_permalink_structure()['category_rewrite_slug'] ?? 'product-category'), '/');
        $caminho = trim((string) wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');

        if ($base !== '' && str_starts_with($caminho, $base.'/')) {
            $partes = explode('/', substr($caminho, strlen($base) + 1));
            $slug = sanitize_title((string) end($partes));
        }
    }

    $destino = $slug !== '' ? conexao_destino_categoria_antiga($slug) : '';

    if ($destino !== '') {
        wp_safe_redirect($destino, 301);
        exit;
    }
});

/**
 * Categorias para a coluna de filtros: cada principal seguida das suas
 * subcategorias, só as que têm livros.
 *
 * @return array<int, array{0: WP_Term, 1: int}> [termo, nível]
 */
function conexao_categorias_em_arvore(): array
{
    $termos = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => true,
        'exclude' => [(int) get_option('default_product_cat')],
    ]);

    if (is_wp_error($termos) || ! $termos) {
        return [];
    }

    // as categorias da loja antiga não voltam para a lista, mesmo que um livro novo seja posto nelas
    $antigas = conexao_categorias_antigas();
    $termos = array_filter($termos, static fn (WP_Term $t): bool => ! isset($antigas[$t->slug]));

    // a ordem definida no painel (arrastar categorias); sem ela, pelo nome
    $ordem = static fn (WP_Term $t): int => (int) get_term_meta($t->term_id, 'order', true);
    usort($termos, static fn (WP_Term $a, WP_Term $b): int => [$ordem($a), $a->name] <=> [$ordem($b), $b->name]);

    $filhas = [];
    foreach ($termos as $t) {
        $filhas[$t->parent][] = $t;
    }

    $lista = [];
    foreach ($filhas[0] ?? [] as $principal) {
        $lista[] = [$principal, 0];

        foreach ($filhas[$principal->term_id] ?? [] as $sub) {
            $lista[] = [$sub, 1];
        }
    }

    return $lista;
}
