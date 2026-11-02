<?php
/**
 * Caixa de compra e frete da página do livro (coluna da direita do Figma).
 * $args['produto']: o produto da loja (simples ou com formatos).
 * $args['frete']: false na página do capítulo, que no Figma não tem o frete.
 */

if (! defined('ABSPATH')) {
    exit;
}

/** @var WC_Product $produto */
$produto = $args['produto'];
$id = $produto->get_id();
$parcelas = 6;
$com_frete = $args['frete'] ?? true;

// o formulário do WooCommerce (formatos) lê o produto global
$GLOBALS['product'] = $produto;
?>
<div class="livro-caixa">
    <div class="compra livro-compra" data-compra data-parcelas="<?php echo esc_attr($parcelas); ?>">
        <p class="livro-compra__preco" data-compra-preco><?php echo wp_kses_post(conexao_preco_livro($produto)); ?></p>

        <p class="livro-compra__parcelas" data-compra-parcelas>
            <?php
            $base = $produto->is_type('variable') ? (float) $produto->get_variation_price('min', true) : (float) $produto->get_price();

            if ($base > 0) {
                printf('ou %dx de %s sem juros', $parcelas, wp_kses_post(wc_price($base / $parcelas)));
            }
            ?>
        </p>

        <?php if ($produto->is_type('variable')) : ?>
            <?php woocommerce_template_single_add_to_cart(); ?>
        <?php else : ?>
            <form class="compra__form" method="post" enctype="multipart/form-data" action="<?php echo esc_url($produto->get_permalink()); ?>">
                <?php if ($produto->is_purchasable() && $produto->is_in_stock()) : ?>
                    <div class="quantity">
                        <label class="tela-leitor" for="compra-qtd">Quantidade</label>
                        <input class="input-text qty" type="number" id="compra-qtd" name="quantity" value="1" min="1" step="1" inputmode="numeric">
                    </div>

                    <?php conexao_botao_compra_principal($produto); ?>

                    <button class="btn btn--contorno btn--bloco livro-compra__carrinho" type="submit" name="add-to-cart" value="<?php echo esc_attr($id); ?>">Adicionar ao carrinho</button>
                <?php elseif (conexao_venda_externa($produto)) : ?>
                    <?php conexao_botao_compra_principal($produto); ?>
                <?php else : ?>
                    <p class="compra__indisponivel">Este título está indisponível no momento.</p>
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <p class="livro-compra__seguro"><?php echo conexao_livro_svg('cadeado'); // phpcs:ignore WordPress.Security.EscapeOutput ?> Compra segura</p>
    </div>

    <?php if ($com_frete) : ?>
    <div class="frete livro-frete" data-frete data-produto="<?php echo esc_attr($id); ?>"
         data-nonce="<?php echo esc_attr(wp_create_nonce('conexao_frete')); ?>">
        <p class="livro-frete__titulo"><?php echo conexao_livro_svg('pin'); // phpcs:ignore WordPress.Security.EscapeOutput ?> Frete e prazo</p>

        <div class="livro-frete__linha">
            <label class="tela-leitor" for="frete-cep">CEP</label>
            <input type="text" id="frete-cep" inputmode="numeric" placeholder="Digite seu CEP" maxlength="9" data-frete-cep>
            <button class="livro-frete__calcular" type="button" data-frete-calcular>Calcular</button>
        </div>

        <div class="frete__resposta" data-frete-resposta role="status"></div>
    </div>
    <?php endif; ?>
</div>
