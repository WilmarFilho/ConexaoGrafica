<?php
/**
 * Moldura das páginas do WooCommerce (minha conta, carrinho, finalizar compra).
 */

if (! defined('ABSPATH')) {
    exit;
}

// o catálogo tem layout próprio (filtros, banner e grade)
if (function_exists('is_shop') && (is_shop() || is_product_taxonomy())) {
    get_template_part('template-parts/catalogo/pagina');

    return;
}

get_header();
?>
<div class="container pagina pagina--loja">
    <?php conexao_trilha(); ?>

    <?php woocommerce_content(); ?>
</div>
<?php
get_footer();
