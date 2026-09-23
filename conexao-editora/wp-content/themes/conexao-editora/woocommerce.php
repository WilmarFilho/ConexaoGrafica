<?php
/**
 * Moldura das páginas do WooCommerce (minha conta, carrinho, finalizar compra).
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();
?>
<div class="container pagina pagina--loja">
    <?php conexao_trilha(); ?>

    <?php woocommerce_content(); ?>
</div>
<?php
get_footer();
