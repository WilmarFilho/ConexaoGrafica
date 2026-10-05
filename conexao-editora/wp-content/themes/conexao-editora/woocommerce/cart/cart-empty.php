<?php
/**
 * Carrinho sem nenhum item: aviso e, embaixo, as novidades da loja.
 */

if (! defined('ABSPATH')) {
    exit;
}

do_action('woocommerce_cart_is_empty');

$novidades = conexao_produtos('lancamentos', 4);
?>
<div class="carrinho-vazio">
    <svg class="carrinho-vazio__icone" width="72" height="72" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <circle cx="12" cy="12" r="11" fill="currentColor"/>
        <circle cx="8.6" cy="9.4" r="1.4" fill="#fff"/>
        <circle cx="15.4" cy="9.4" r="1.4" fill="#fff"/>
        <path d="M6.9 12.8c.6.9 1.1 1.5 1.1 2.1a1.1 1.1 0 0 1-2.2 0c0-.6.5-1.2 1.1-2.1z" fill="#fff"/>
        <path d="M8.2 17.2c1-1.5 2.4-2.3 3.8-2.3s2.8.8 3.8 2.3" fill="none" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/>
    </svg>

    <h2>Seu carrinho está vazio!</h2>
    <p class="carrinho-vazio__pontos" aria-hidden="true"><span></span><span></span><span></span></p>
    <p>Escolha um título no catálogo para começar.</p>
    <p><a class="btn btn--azul" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Ver o catálogo</a></p>
</div>

<?php if ($novidades) : ?>
    <section class="carrinho-novidades">
        <h2>Novidades na loja</h2>

        <div class="carrinho-novidades__grade">
            <?php foreach ($novidades as $produto) : ?>
                <?php conexao_card_produto($produto); ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
