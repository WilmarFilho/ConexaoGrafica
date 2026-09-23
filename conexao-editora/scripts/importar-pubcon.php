<?php
/**
 * Importa o catálogo exportado da loja atual (scripts/dados/pubcon-produtos.json)
 * para este ambiente. Roda quantas vezes precisar: casa pelo SKU ou pelo nome.
 *
 * Uso: wp eval-file /scripts/importar-pubcon.php
 */

if (! class_exists('WooCommerce')) {
    WP_CLI::error('WooCommerce não está ativo.');
}

require_once ABSPATH.'wp-admin/includes/media.php';
require_once ABSPATH.'wp-admin/includes/file.php';
require_once ABSPATH.'wp-admin/includes/image.php';

$arquivo = getenv('CONEXAO_DADOS')
    ?: (file_exists('/scripts/dados/pubcon-produtos.json')
        ? '/scripts/dados/pubcon-produtos.json'
        : __DIR__.'/dados/pubcon-produtos.json');

if (! file_exists($arquivo)) {
    WP_CLI::error("não achei {$arquivo}");
}

$produtos = json_decode((string) file_get_contents($arquivo), true);

if (! is_array($produtos)) {
    WP_CLI::error('JSON inválido');
}

/**
 * As categorias da loja atual são muitas e misturam formato com assunto.
 * Aqui elas viram as oito categorias do layout novo; a categoria original
 * continua gravada no produto, para não perder nada.
 */
$mapa = [
    'Livro Oftalmologia' => 'Medicina',
    'E-book Oftalmologia' => 'Medicina',
    'Livro Mastologia' => 'Medicina',
    'E-book Mastologia' => 'Medicina',
    'Livro Ultrassonografia' => 'Medicina',
    'E-book Ultrassonografia' => 'Medicina',
    'Livro Dermatologia' => 'Medicina',
    'E-book Dermatologia' => 'Medicina',
    'Livro da SBUS' => 'Medicina',
    'Ebook SBUS' => 'Medicina',
    'Livro da CBO' => 'Medicina',
    'Ebook da CBO' => 'Medicina',
    'Fertilidade' => 'Medicina',
    'História' => 'História',
    'Política' => 'História',
    'Direito' => 'Direito',
    'Livro Biografia' => 'Biografia',
    'E-book Biografia' => 'Biografia',
    'E-books Relatos' => 'Biografia',
    'Livro Educação Familiar' => 'Educação Familiar',
    'Ebook Educação Familiar' => 'Educação Familiar',
    'Livro Crônicas' => 'Crônica',
    'Fé' => 'Religião',
];

$criados = 0;
$atualizados = 0;
$capas = 0;

foreach ($produtos as $dado) {
    $existente = null;

    if (! empty($dado['sku'])) {
        $id = wc_get_product_id_by_sku($dado['sku']);
        $existente = $id ? wc_get_product($id) : null;
    }

    if (! $existente) {
        $pagina = get_page_by_path($dado['slug'], OBJECT, 'product');
        $existente = $pagina ? wc_get_product($pagina->ID) : null;
    }

    $produto = $existente ?: new WC_Product_Simple();

    $produto->set_name($dado['nome']);
    $produto->set_slug($dado['slug']);
    $produto->set_status($dado['status'] === 'draft' ? 'draft' : 'publish');
    $produto->set_description((string) $dado['descricao']);
    $produto->set_short_description((string) $dado['resumo']);
    $produto->set_regular_price((string) $dado['preco']);
    $produto->set_sale_price((string) $dado['promocao']);
    $produto->set_stock_status($dado['estoque'] ?: 'instock');
    $produto->set_virtual((bool) $dado['virtual']);
    $produto->set_date_created($dado['data']);

    if (! empty($dado['sku']) && ! wc_get_product_id_by_sku($dado['sku'])) {
        $produto->set_sku($dado['sku']);
    }

    if ($dado['peso'] !== '') {
        $produto->set_weight((string) $dado['peso']);
    }

    [$c, $l, $a] = $dado['dimensoes'];
    $produto->set_length((string) $c);
    $produto->set_width((string) $l);
    $produto->set_height((string) $a);

    $id = $produto->save();

    // categorias: as do layout novo mais a original, para rastrear a origem
    $nomes = [];
    foreach ($dado['categorias'] as $original) {
        $nomes[] = $mapa[$original] ?? $original;
        $nomes[] = $original;
    }

    $termos = [];
    foreach (array_unique($nomes) as $nome) {
        $termo = term_exists($nome, 'product_cat') ?: wp_insert_term($nome, 'product_cat');

        if (! is_wp_error($termo)) {
            $termos[] = (int) $termo['term_id'];
        }
    }

    wp_set_object_terms($id, $termos, 'product_cat');

    // capa: baixada uma vez só
    if (! has_post_thumbnail($id) && ! empty($dado['capa'])) {
        $anexo = media_sideload_image($dado['capa'], $id, $dado['nome'], 'id');

        if (! is_wp_error($anexo)) {
            set_post_thumbnail($id, $anexo);
            $capas++;
        } else {
            WP_CLI::warning("capa de {$dado['nome']}: ".$anexo->get_error_message());
        }
    }

    $existente ? $atualizados++ : $criados++;
}

WP_CLI::success("{$criados} criados, {$atualizados} atualizados, {$capas} capas baixadas");
