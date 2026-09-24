<?php
/**
 * Tema da Conexão Editora.
 */

if (! defined('ABSPATH')) {
    exit;
}

define('CONEXAO_VERSION', '0.1.3');

require_once get_template_directory().'/inc/icons.php';
require_once get_template_directory().'/inc/template-tags.php';
require_once get_template_directory().'/inc/conta.php';
require_once get_template_directory().'/inc/contato.php';
require_once get_template_directory().'/inc/menu.php';
require_once get_template_directory().'/inc/faq.php';

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('customize-selective-refresh-widgets');
    add_theme_support('responsive-embeds');

    add_theme_support('woocommerce', [
        'thumbnail_image_width' => 400,
        'single_image_width' => 800,
    ]);
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');

    register_nav_menus([
        'principal' => 'Menu principal',
        'topo' => 'Barra do topo',
        'rodape-catalogo' => 'Rodapé · Catálogo',
        'rodape-editora' => 'Rodapé · Editora',
        'rodape-ajuda' => 'Rodapé · Ajuda',
        'rodape-politicas' => 'Rodapé · Políticas',
    ]);
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'conexao-fonts',
        'https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,300..900;1,400&display=swap',
        [],
        null
    );

    wp_enqueue_style(
        'conexao',
        get_template_directory_uri().'/assets/css/theme.css',
        ['conexao-fonts'],
        conexao_versao_arquivo('/assets/css/theme.css')
    );

    wp_enqueue_script(
        'conexao',
        get_template_directory_uri().'/assets/js/theme.js',
        [],
        conexao_versao_arquivo('/assets/js/theme.js'),
        true
    );
}, 20);

/** O carrinho do cabeçalho atualiza sem recarregar a página. */
add_filter('woocommerce_add_to_cart_fragments', function (array $fragments) {
    ob_start();
    conexao_cart_button();

    $fragments['a.hdr-acao--carrinho'] = ob_get_clean();

    return $fragments;
});

/** Enquanto as capas reais não são migradas, o lugar delas fica marcado. */
add_filter('woocommerce_placeholder_img_src', fn () => get_template_directory_uri().'/assets/img/capa-placeholder.png');

/** Produtos por página na listagem da loja. */
add_filter('loop_shop_per_page', fn () => 12, 20);

/** Sem a barra do admin atrapalhando o layout durante o desenvolvimento. */
add_filter('show_admin_bar', '__return_false');
