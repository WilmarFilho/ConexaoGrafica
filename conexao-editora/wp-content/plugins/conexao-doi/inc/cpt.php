<?php
/**
 * Tipos de conteúdo: `publicacao` (livro) e `capitulo` (filho do livro por
 * post_parent). Ninguém edita no WordPress: o Hub Editora manda tudo pela API.
 *
 * Endereços: /livro/{slug}/ e /livro/{slug}/capitulo/{nn}/ (nn = sequência do
 * capítulo com dois dígitos). O slug do livro deixa de mudar quando o DOI já
 * foi enviado à Crossref; antes disso, o endereço antigo responde 301.
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_DOI_META_HUB_ID = '_conexao_hub_id';
const CONEXAO_DOI_META_DOI = '_conexao_doi';
const CONEXAO_DOI_META_DADOS = '_conexao_dados';
const CONEXAO_DOI_META_ISBN_PRINT = '_conexao_isbn_print';
const CONEXAO_DOI_META_ISBN_EBOOK = '_conexao_isbn_ebook';
const CONEXAO_DOI_META_STATUS = '_conexao_status';           // published | unpublished
const CONEXAO_DOI_META_DOI_STATUS = '_conexao_doi_status';   // none … registered
const CONEXAO_DOI_META_SEQUENCIA = '_conexao_sequencia';     // capítulo: ordem no livro
const CONEXAO_DOI_META_AREA = '_conexao_area';               // livro: área de conhecimento
const CONEXAO_DOI_META_SLUG_ANTIGO = '_conexao_slug_antigo'; // livro: slugs anteriores (vários)

/** Ordem dos estados do DOI, para comparar "já foi enviado?". */
function conexao_doi_estados_doi(): array
{
    return ['none' => 0, 'validated' => 1, 'ready' => 2, 'sent' => 3, 'processing' => 4, 'registered' => 5, 'failed' => 3];
}

/** O DOI já saiu para a Crossref? A partir daí o slug não muda mais. */
function conexao_doi_slug_travado(string $doi_status): bool
{
    return (conexao_doi_estados_doi()[$doi_status] ?? 0) >= 3;
}

function conexao_doi_registra_cpts(): void
{
    $comum = [
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => false,
        'has_archive' => false,
        'exclude_from_search' => false,
        'publicly_queryable' => true,
        'rewrite' => false,
        'query_var' => true,
        'supports' => false,
        'taxonomies' => ['autor'],
        'capability_type' => 'post',
        'map_meta_cap' => true,
        // ninguém cria à mão: só a API do Hub
        'capabilities' => ['create_posts' => 'do_not_allow'],
    ];

    register_post_type('publicacao', array_merge($comum, [
        'labels' => [
            'name' => 'Publicações (DOI)',
            'singular_name' => 'Publicação',
            'menu_name' => 'Publicações DOI',
            'all_items' => 'Livros',
            'edit_item' => 'Publicação',
            'view_item' => 'Ver página do livro',
            'search_items' => 'Buscar publicações',
            'not_found' => 'Nenhuma publicação recebida do Hub Editora.',
        ],
        'menu_icon' => 'dashicons-book-alt',
        'menu_position' => 26,
    ]));

    register_post_type('capitulo', array_merge($comum, [
        'labels' => [
            'name' => 'Capítulos',
            'singular_name' => 'Capítulo',
            'menu_name' => 'Capítulos',
            'all_items' => 'Capítulos',
            'edit_item' => 'Capítulo',
            'view_item' => 'Ver página do capítulo',
            'search_items' => 'Buscar capítulos',
            'not_found' => 'Nenhum capítulo recebido do Hub Editora.',
        ],
        'show_in_menu' => 'edit.php?post_type=publicacao',
    ]));

    // a taxonomia `autor` é do tema (produtos); as publicações entram nela para
    // a página /autores/ e os filtros continuarem valendo
    if (taxonomy_exists('autor')) {
        register_taxonomy_for_object_type('autor', 'publicacao');
        register_taxonomy_for_object_type('autor', 'capitulo');
    }
}

// depois do tema registrar a taxonomia `autor` (prioridade padrão)
add_action('init', 'conexao_doi_registra_cpts', 20);

function conexao_doi_regras_de_url(): void
{
    add_rewrite_rule('^livro/([^/]+)/capitulo/([0-9]+)/?$', 'index.php?post_type=capitulo&conexao_livro=$matches[1]&conexao_capitulo=$matches[2]', 'top');
    add_rewrite_rule('^livro/([^/]+)/?$', 'index.php?post_type=publicacao&name=$matches[1]', 'top');
}

add_action('init', 'conexao_doi_regras_de_url', 21);

add_filter('query_vars', function (array $vars): array {
    $vars[] = 'conexao_livro';
    $vars[] = 'conexao_capitulo';

    return $vars;
});

/**
 * /livro/{slug}/capitulo/{nn}/ vira o post do capítulo (pai = livro do slug,
 * sequência = nn). Sem par → 404 de verdade.
 */
add_filter('request', function (array $qv): array {
    if (empty($qv['conexao_livro'])) {
        return $qv;
    }

    $livro = conexao_doi_livro_por_slug((string) $qv['conexao_livro']);
    $capitulo = $livro ? conexao_doi_capitulo_por_sequencia($livro->ID, (int) $qv['conexao_capitulo']) : null;

    if (! $capitulo) {
        return ['error' => '404'];
    }

    return ['post_type' => 'capitulo', 'p' => $capitulo->ID];
});

function conexao_doi_livro_por_slug(string $slug): ?WP_Post
{
    $posts = get_posts([
        'post_type' => 'publicacao',
        'post_status' => 'publish',
        'name' => $slug,
        'numberposts' => 1,
    ]);

    return $posts[0] ?? null;
}

function conexao_doi_capitulo_por_sequencia(int $livro_id, int $sequencia): ?WP_Post
{
    if ($sequencia < 1) {
        return null;
    }

    $posts = get_posts([
        'post_type' => 'capitulo',
        'post_status' => 'publish',
        'post_parent' => $livro_id,
        'numberposts' => 1,
        'meta_key' => CONEXAO_DOI_META_SEQUENCIA,
        'meta_value' => $sequencia,
    ]);

    return $posts[0] ?? null;
}

/** Capítulos de um livro na ordem do sumário. */
function conexao_doi_capitulos_do_livro(int $livro_id): array
{
    return get_posts([
        'post_type' => 'capitulo',
        'post_status' => 'publish',
        'post_parent' => $livro_id,
        'numberposts' => -1,
        'orderby' => 'menu_order',
        'order' => 'ASC',
    ]);
}

/** Número do capítulo no endereço: 2 dígitos, 3 a partir do 100. */
function conexao_doi_numero_url(int $sequencia): string
{
    return str_pad((string) $sequencia, 2, '0', STR_PAD_LEFT);
}

add_filter('post_type_link', function (string $link, WP_Post $post): string {
    if ($post->post_type === 'publicacao') {
        return home_url('/livro/'.$post->post_name.'/');
    }

    if ($post->post_type === 'capitulo') {
        $livro = $post->post_parent ? get_post($post->post_parent) : null;
        $sequencia = (int) get_post_meta($post->ID, CONEXAO_DOI_META_SEQUENCIA, true);

        if ($livro && $sequencia) {
            return home_url('/livro/'.$livro->post_name.'/capitulo/'.conexao_doi_numero_url($sequencia).'/');
        }
    }

    return $link;
}, 10, 2);

/**
 * Endereço antigo do livro (slug trocado antes do depósito) responde 301 para
 * o novo, inclusive para os capítulos.
 */
add_action('template_redirect', function (): void {
    if (! is_404()) {
        return;
    }

    $caminho = trim((string) wp_parse_url(add_query_arg([]), PHP_URL_PATH), '/');
    $base = trim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');

    if ($base !== '' && str_starts_with($caminho, $base.'/')) {
        $caminho = substr($caminho, strlen($base) + 1);
    }

    if (! preg_match('#^livro/([^/]+)(?:/capitulo/([0-9]+))?/?$#', $caminho, $m)) {
        return;
    }

    $livros = get_posts([
        'post_type' => 'publicacao',
        'post_status' => 'publish',
        'numberposts' => 1,
        'meta_key' => CONEXAO_DOI_META_SLUG_ANTIGO,
        'meta_value' => $m[1],
    ]);

    // sem livro com esse slug antigo, ou o slug é o atual (capítulo inexistente): 404 mesmo
    if (! $livros || $livros[0]->post_name === $m[1]) {
        return;
    }

    $destino = get_permalink($livros[0]);

    if (! empty($m[2])) {
        $capitulo = conexao_doi_capitulo_por_sequencia($livros[0]->ID, (int) $m[2]);
        $destino = $capitulo ? get_permalink($capitulo) : $destino;
    }

    wp_safe_redirect($destino, 301);
    exit;
});

/** Os templates do plugin respondem pelas páginas dos dois tipos. */
add_filter('template_include', function (string $template): string {
    if (is_singular('publicacao')) {
        return CONEXAO_DOI_PASTA.'/templates/single-publicacao.php';
    }

    if (is_singular('capitulo')) {
        return CONEXAO_DOI_PASTA.'/templates/single-capitulo.php';
    }

    return $template;
}, 20);

add_filter('body_class', function (array $classes): array {
    if (is_singular(['publicacao', 'capitulo'])) {
        $classes[] = 'conexao-doi';
    }

    return $classes;
});
