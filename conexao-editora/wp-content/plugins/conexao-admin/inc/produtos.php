<?php
/**
 * Produtos: colunas na ordem do quadro (DOI logo depois de Nome, sem as
 * colunas de SEO), linha de apoio sob o nome (ID, formato, pré-venda), filtro
 * por DOI e, na edição, a caixa "DOI do livro" redesenhada (só leitura) e os
 * títulos das caixas próprias com a etiqueta do plugin. Nada é gravado.
 */

if (! defined('ABSPATH')) {
    exit;
}

// ---- Lista ---------------------------------------------------------------------

const CONEXAO_ADMIN_COLUNAS = ['cb', 'thumb', 'name', 'conexao_doi', 'sku', 'is_in_stock', 'price', 'product_cat', 'taxonomy-autor', 'taxonomy-colecao', 'featured', 'date'];

// fora da lista: SEO do Rank Math, Tags e GTIN/UPC/EAN/ISBN (o ISBN do livro fica nos campos do tema)
add_filter('manage_edit-product_columns', function (array $colunas): array {
    foreach (array_keys($colunas) as $chave) {
        if (str_starts_with((string) $chave, 'rank_math') || in_array($chave, ['product_tag', 'global_unique_id'], true)) {
            unset($colunas[$chave]);
        }
    }

    $ordenadas = [];

    foreach (CONEXAO_ADMIN_COLUNAS as $chave) {
        if ($chave === 'featured') {
            // o que vier de outros plugins fica antes do destaque e da data
            foreach ($colunas as $outra => $rotulo) {
                if (! in_array($outra, CONEXAO_ADMIN_COLUNAS, true)) {
                    $ordenadas[$outra] = $rotulo;
                }
            }
        }

        if (isset($colunas[$chave])) {
            $ordenadas[$chave] = $colunas[$chave];
        }
    }

    return $ordenadas;
}, 99);

// filtro de Marcas só quando houver marca cadastrada (a editora não usa)
add_filter('woocommerce_products_admin_list_table_filters', function (array $filtros): array {
    if (isset($filtros['product_brand']) && taxonomy_exists('product_brand') && ! (int) wp_count_terms(['taxonomy' => 'product_brand', 'hide_empty' => false])) {
        unset($filtros['product_brand']);
    }

    return $filtros;
}, 20);

// sob o nome: ID, formato e pré-venda (o "ID" sai das ações da linha)
add_action('manage_product_posts_custom_column', function (string $coluna, int $id): void {
    if ($coluna !== 'name') {
        return;
    }

    $partes = ['ID: '.$id, conexao_admin_formato_produto($id)];

    if (get_post_meta($id, '_conexao_pre_venda', true) === 'yes') {
        $partes[] = '<span class="cxa-cor-aviso">pré-venda</span>';
    }

    echo '<div class="cxa-linha-apoio">'.wp_kses_post(implode(' · ', array_filter($partes))).'</div>';
}, 20, 2);

add_filter('post_row_actions', function (array $acoes, WP_Post $post): array {
    if ($post->post_type === 'product') {
        unset($acoes['id']);
    }

    return $acoes;
}, 110, 2);

/** "Físico", "E-book" ou "Físico + E-book", pelo que o produto entrega. */
function conexao_admin_formato_produto(int $id): string
{
    $produto = function_exists('wc_get_product') ? wc_get_product($id) : null;

    if (! $produto) {
        return '';
    }

    $digital = function_exists('conexao_biblioteca_e_digital') ? conexao_biblioteca_e_digital($id) : ($produto->is_virtual() || $produto->is_downloadable());

    if ($produto->is_type('simple') && $produto->is_virtual()) {
        return 'E-book';
    }

    return $digital ? 'Físico + E-book' : 'Físico';
}

// ---- Filtro por DOI --------------------------------------------------------------

/** Produtos ligados a um livro DOI, por situação: ['registrado' => [ids], 'atribuido' => [ids]]. */
function conexao_admin_produtos_por_doi(): array
{
    global $wpdb;

    $chave_woo = defined('CONEXAO_DOI_META_WOO') ? CONEXAO_DOI_META_WOO : '_conexao_woo_produto';
    $chave_estado = defined('CONEXAO_DOI_META_DOI_STATUS') ? CONEXAO_DOI_META_DOI_STATUS : '_conexao_doi_status';

    $linhas = $wpdb->get_results($wpdb->prepare(
        "SELECT w.meta_value AS produto, COALESCE(s.meta_value, 'none') AS estado
         FROM {$wpdb->posts} p
         JOIN {$wpdb->postmeta} w ON w.post_id = p.ID AND w.meta_key = %s
         LEFT JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = %s
         WHERE p.post_type = 'publicacao' AND p.post_status = 'publish'",
        $chave_woo,
        $chave_estado
    ));

    $grupos = ['registrado' => [], 'atribuido' => []];

    foreach ((array) $linhas as $linha) {
        $grupos[$linha->estado === 'registered' ? 'registrado' : 'atribuido'][] = (int) $linha->produto;
    }

    return $grupos;
}

add_action('restrict_manage_posts', function (string $tipo, string $onde = 'top'): void {
    if ($tipo !== 'product' || $onde !== 'top' || ! post_type_exists('publicacao')) {
        return;
    }

    $atual = isset($_GET['cxa_doi']) ? sanitize_key(wp_unslash($_GET['cxa_doi'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    $opcoes = ['' => 'Qualquer DOI', 'registrado' => 'DOI: registrado', 'atribuido' => 'DOI: atribuído', 'sem' => 'Sem DOI'];

    echo '<label class="screen-reader-text" for="cxa-doi">Filtrar por DOI</label><select name="cxa_doi" id="cxa-doi">';

    foreach ($opcoes as $valor => $rotulo) {
        printf('<option value="%s"%s>%s</option>', esc_attr($valor), selected($atual, $valor, false), esc_html($rotulo));
    }

    echo '</select>';
}, 20, 2);

add_action('pre_get_posts', function (WP_Query $consulta): void {
    global $pagenow;

    if (! is_admin() || ! $consulta->is_main_query() || $pagenow !== 'edit.php' || $consulta->get('post_type') !== 'product' || empty($_GET['cxa_doi'])) { // phpcs:ignore WordPress.Security.NonceVerification
        return;
    }

    $filtro = sanitize_key(wp_unslash($_GET['cxa_doi'])); // phpcs:ignore WordPress.Security.NonceVerification
    $grupos = conexao_admin_produtos_por_doi();

    if ($filtro === 'sem') {
        $consulta->set('post__not_in', array_merge($grupos['registrado'], $grupos['atribuido']));
    } elseif (isset($grupos[$filtro])) {
        $consulta->set('post__in', $grupos[$filtro] ?: [0]);
    }
});

// ---- Edição --------------------------------------------------------------------

add_action('add_meta_boxes_product', function (WP_Post $post): void {
    global $wp_meta_boxes;

    // Marcas não são usadas pela editora: a caixa só aparece se existir alguma
    if (taxonomy_exists('product_brand') && ! (int) wp_count_terms(['taxonomy' => 'product_brand', 'hide_empty' => false])) {
        remove_meta_box('product_branddiv', 'product', 'side');
        remove_meta_box('tagsdiv-product_brand', 'product', 'side');
    }

    // Livro digital (Conexão Biblioteca): só o título ganha a etiqueta do plugin
    foreach ((array) ($wp_meta_boxes['product']['side'] ?? []) as $prioridade => $caixas) {
        if (! empty($caixas['conexao-biblioteca']) && is_array($caixas['conexao-biblioteca'])) {
            $wp_meta_boxes['product']['side'][$prioridade]['conexao-biblioteca']['title'] = 'Livro digital <span class="cxa-eb cxa-eb--acento">Conexão Biblioteca</span>';
            conexao_admin_nome_simples('product', 'conexao-biblioteca', 'Livro digital');
        }
    }

    // DOI do livro (Conexão DOI): mesma caixa, mesmo id, novo desenho
    if (function_exists('conexao_doi_livro_do_produto_admin')) {
        $livro = conexao_doi_livro_do_produto_admin($post->ID);
        $estado = $livro ? ((string) get_post_meta($livro, CONEXAO_DOI_META_DOI_STATUS, true) ?: 'none') : '';
        $selo = ! $livro ? '<span class="cxa-selo">sem DOI</span>' : ($estado === 'registered' ? '<span class="cxa-selo cxa-selo--ok">Registrado</span>' : ($estado === 'failed' ? '<span class="cxa-selo cxa-selo--perigo">Falhou</span>' : '<span class="cxa-selo cxa-selo--info">Atribuído</span>'));

        add_meta_box(
            'conexao-doi-produto',
            '<span class="cxa-titulo-caixa">DOI do livro <span class="cxa-eb cxa-eb--acento">Conexão DOI</span></span>'.$selo,
            'conexao_admin_caixa_doi',
            'product',
            'side',
            'high',
            ['__widget_basename' => 'DOI do livro']
        );
    }
}, 20);

function conexao_admin_caixa_doi(WP_Post $post): void
{
    $livro = conexao_doi_livro_do_produto_admin($post->ID);

    if (! $livro) {
        echo '<div class="cxa-doi"><span class="cxa-doi__icone cxa-doi__icone--vazio" aria-hidden="true"></span><div><div class="cxa-forte">Sem DOI</div><div class="cxa-sub">Nenhum livro DOI ligado a este produto.</div></div></div>';
        echo '<p class="cxa-ajuda">O livro é cadastrado no Hub Editora (Publicações › Livros) e ligado ao produto lá.</p>';

        return;
    }

    $doi = (string) get_post_meta($livro, CONEXAO_DOI_META_DOI, true);
    $estado = (string) get_post_meta($livro, CONEXAO_DOI_META_DOI_STATUS, true) ?: 'none';
    $fora = get_post_meta($livro, CONEXAO_DOI_META_STATUS, true) === 'unpublished';
    $tom = $estado === 'registered' ? 'ok' : ($estado === 'failed' ? 'perigo' : 'aviso');
    ?>
    <div class="cxa-doi">
        <span class="cxa-doi__icone cxa-doi__icone--<?php echo esc_attr($tom); ?>" aria-hidden="true"></span>
        <div>
            <div class="cxa-forte"><?php echo esc_html(conexao_doi_rotulo_registro($estado)); ?></div>
            <?php if ($doi !== '') : ?>
                <a class="cxa-mono" href="<?php echo esc_url('https://doi.org/'.$doi); ?>" target="_blank" rel="noopener"><?php echo esc_html($doi); ?></a>
            <?php endif; ?>
        </div>
    </div>
    <div class="cxa-linhas">
        <div class="cxa-linhas__item"><span>Livro</span><a href="<?php echo esc_url((string) get_permalink($livro)); ?>" target="_blank"><?php echo esc_html(get_the_title($livro)); ?> ↗</a></div>
        <div class="cxa-linhas__item"><span>Página</span><span class="cxa-sub"><?php echo $fora ? 'fora de catálogo; a página do DOI continua no ar' : '/product/ redireciona para a página do livro'; ?></span></div>
    </div>
    <p class="cxa-ajuda">Somente leitura. O livro é cadastrado no Hub Editora.</p>
    <?php
}
