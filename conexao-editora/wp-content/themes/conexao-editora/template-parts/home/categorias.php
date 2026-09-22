<?php
/**
 * Atalhos para as categorias de livro.
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! taxonomy_exists('product_cat')) {
    return;
}

$categorias = get_terms([
    'taxonomy' => 'product_cat',
    'hide_empty' => false,
    'number' => 8,
    'orderby' => 'name',
    'exclude' => [(int) get_option('default_product_cat')],
]);

if (is_wp_error($categorias) || ! $categorias) {
    return;
}
?>
<section class="categorias">
    <div class="container">
        <?php conexao_secao_titulo('Categorias em destaque', class_exists('WooCommerce') ? wc_get_page_permalink('shop') : ''); ?>

        <ul class="categorias__lista">
            <?php foreach ($categorias as $categoria) : ?>
                <li>
                    <a class="categoria" href="<?php echo esc_url(get_term_link($categoria)); ?>">
                        <?php conexao_the_icon(conexao_icone_categoria($categoria->slug), 34); ?>
                        <span><?php echo esc_html($categoria->name); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
