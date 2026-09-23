<?php
/**
 * Carrinho sem nenhum item.
 */

if (! defined('ABSPATH')) {
    exit;
}

do_action('woocommerce_cart_is_empty');
?>
<div class="carrinho-vazio">
    <h2>Seu carrinho está vazio</h2>
    <p>Escolha um título no catálogo para começar.</p>
    <p><a class="btn btn--azul" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Ver o catálogo</a></p>
</div>
