<?php
/**
 * Home da loja.
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();

get_template_part('template-parts/home/hero');
get_template_part('template-parts/home/vitrine', null, [
    'titulo' => 'Lançamentos',
    'tipo' => 'lancamentos',
    'url' => class_exists('WooCommerce') ? wc_get_page_permalink('shop') : home_url('/livros/'),
]);
get_template_part('template-parts/home/categorias');
get_template_part('template-parts/home/destaque');
get_template_part('template-parts/home/vitrine', null, [
    'titulo' => 'Mais vendidos',
    'tipo' => 'mais-vendidos',
    'url' => home_url('/mais-vendidos/'),
]);
get_template_part('template-parts/home/publique');
get_template_part('template-parts/home/conteudos');

get_footer();
