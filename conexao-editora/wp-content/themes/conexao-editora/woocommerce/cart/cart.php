<?php
/**
 * Carrinho no formato do layout: cada item em um cartão e o resumo do pedido
 * embaixo. Substitui o template do WooCommerce.
 */

if (! defined('ABSPATH')) {
    exit;
}

do_action('woocommerce_before_cart');
?>
<form class="carrinho" action="<?php echo esc_url(wc_get_cart_url()); ?>" method="post" data-carrinho>
    <?php do_action('woocommerce_before_cart_table'); ?>

    <div class="carrinho__cabecalho" aria-hidden="true">
        <span>Item</span>
        <span>Quantidade</span>
        <span>Preço</span>
        <span>Valor total</span>
        <span></span>
    </div>

    <div class="carrinho__itens">
        <?php
        do_action('woocommerce_before_cart_contents');

        foreach (WC()->cart->get_cart() as $chave => $item) {
            $produto = apply_filters('woocommerce_cart_item_product', $item['data'], $item, $chave);
            $id = apply_filters('woocommerce_cart_item_product_id', $item['product_id'], $item, $chave);

            if (! $produto || ! $produto->exists() || $item['quantity'] <= 0 || ! apply_filters('woocommerce_cart_item_visible', true, $item, $chave)) {
                continue;
            }

            $link = apply_filters('woocommerce_cart_item_permalink', $produto->is_visible() ? $produto->get_permalink($item) : '', $item, $chave);
            $autores = conexao_autores($produto);
            $isbn = $produto->get_sku();
            ?>
            <article class="item-carrinho <?php echo esc_attr(apply_filters('woocommerce_cart_item_class', 'cart_item', $item, $chave)); ?>">
                <div class="item-carrinho__produto">
                    <span class="item-carrinho__capa">
                        <?php
                        $imagem = apply_filters('woocommerce_cart_item_thumbnail', $produto->get_image('woocommerce_thumbnail'), $item, $chave);
                        echo $link ? '<a href="'.esc_url($link).'">'.$imagem.'</a>' : $imagem; // phpcs:ignore WordPress.Security.EscapeOutput
                        ?>
                    </span>

                    <div class="item-carrinho__texto">
                        <?php if ($isbn) : ?>
                            <p class="item-carrinho__isbn">ISBN <?php echo esc_html($isbn); ?></p>
                        <?php endif; ?>

                        <h2 class="item-carrinho__titulo">
                            <?php
                            $nome = apply_filters('woocommerce_cart_item_name', $produto->get_name(), $item, $chave);
                            echo $link ? '<a href="'.esc_url($link).'">'.esc_html(wp_strip_all_tags($nome)).'</a>' : esc_html(wp_strip_all_tags($nome));
                            ?>
                        </h2>

                        <?php if ($autores) : ?>
                            <p class="item-carrinho__autores"><?php echo esc_html($autores); ?></p>
                        <?php endif; ?>

                        <?php echo wc_get_formatted_cart_item_data($item); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    </div>
                </div>

                <div class="item-carrinho__quantidade">
                    <?php if ($produto->is_sold_individually()) : ?>
                        <span class="passo passo--fixo">1</span>
                        <input type="hidden" name="cart[<?php echo esc_attr($chave); ?>][qty]" value="1">
                    <?php else : ?>
                        <div class="passo">
                            <button class="passo__botao" type="button" data-passo="-1" aria-label="Diminuir quantidade">−</button>
                            <label class="tela-leitor" for="qtd-<?php echo esc_attr($chave); ?>">Quantidade</label>
                            <input class="passo__campo" type="number" id="qtd-<?php echo esc_attr($chave); ?>"
                                   name="cart[<?php echo esc_attr($chave); ?>][qty]"
                                   value="<?php echo esc_attr($item['quantity']); ?>"
                                   min="0" step="1" inputmode="numeric"
                                   <?php echo $produto->get_max_purchase_quantity() > 0 ? 'max="'.esc_attr($produto->get_max_purchase_quantity()).'"' : ''; ?>>
                            <button class="passo__botao" type="button" data-passo="1" aria-label="Aumentar quantidade">+</button>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="item-carrinho__preco" data-rotulo="Preço">
                    <?php echo apply_filters('woocommerce_cart_item_price', WC()->cart->get_product_price($produto), $item, $chave); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </div>

                <div class="item-carrinho__total" data-rotulo="Valor total">
                    <?php echo apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($produto, $item['quantity']), $item, $chave); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </div>

                <div class="item-carrinho__remover">
                    <a class="remover" href="<?php echo esc_url(wc_get_cart_remove_url($chave)); ?>"
                       aria-label="Remover <?php echo esc_attr(wp_strip_all_tags($produto->get_name())); ?> do carrinho">
                        <?php conexao_the_icon('lixeira', 20); ?>
                    </a>
                </div>
            </article>
            <?php
        }

        do_action('woocommerce_cart_contents');
        ?>
    </div>

    <?php wp_nonce_field('woocommerce-cart', 'woocommerce-cart-nonce'); ?>
    <button class="tela-leitor" type="submit" name="update_cart" value="Atualizar carrinho">Atualizar carrinho</button>

    <?php do_action('woocommerce_cart_contents'); ?>
    <?php do_action('woocommerce_after_cart_contents'); ?>
    <?php do_action('woocommerce_after_cart_table'); ?>
</form>

<?php do_action('woocommerce_before_cart_collaterals'); ?>

<section class="resumo">
    <div class="resumo__esquerda">
        <h2>Resumo do Pedido</h2>
        <p>Tudo pronto para o próximo passo? Revise os detalhes da sua compra abaixo antes de finalizar.</p>

        <?php if (wc_coupons_enabled()) : ?>
            <p class="resumo__cupom-rotulo">Tem um código de cupom de desconto?</p>

            <form class="resumo__cupom" action="<?php echo esc_url(wc_get_cart_url()); ?>" method="post">
                <label class="tela-leitor" for="coupon_code">Código do cupom</label>
                <span class="resumo__cupom-campo">
                    <?php conexao_the_icon('cupom', 20); ?>
                    <input type="text" name="coupon_code" id="coupon_code" placeholder="Digite o número do cupom" value="">
                </span>
                <button type="submit" name="apply_coupon" value="Aplicar cupom" aria-label="Aplicar cupom">→</button>
                <?php wp_nonce_field('woocommerce-cart', 'woocommerce-cart-nonce'); ?>
            </form>

            <?php foreach (WC()->cart->get_applied_coupons() as $cupom) : ?>
                <p class="resumo__cupom-ativo">
                    <?php echo esc_html(wc_cart_totals_coupon_label($cupom, false)); ?>
                    <a href="<?php echo esc_url(add_query_arg('remove_coupon', rawurlencode($cupom), wc_get_cart_url())); ?>">remover</a>
                </p>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="resumo__direita">
        <dl class="resumo__valores">
            <div>
                <dt>Subtotal</dt>
                <dd><?php wc_cart_totals_subtotal_html(); ?></dd>
            </div>

            <?php foreach (WC()->cart->get_coupons() as $codigo => $cupom) : ?>
                <div class="resumo__desconto">
                    <dt><?php wc_cart_totals_coupon_label($cupom); ?></dt>
                    <dd><?php wc_cart_totals_coupon_html($cupom); ?></dd>
                </div>
            <?php endforeach; ?>

            <?php if (WC()->cart->needs_shipping() && WC()->cart->show_shipping()) : ?>
                <div class="resumo__frete">
                    <dt>Frete</dt>
                    <dd><?php conexao_frete_resumo(); ?></dd>
                </div>
            <?php endif; ?>

            <?php foreach (WC()->cart->get_fees() as $taxa) : ?>
                <div>
                    <dt><?php echo esc_html($taxa->name); ?></dt>
                    <dd><?php wc_cart_totals_fee_html($taxa); ?></dd>
                </div>
            <?php endforeach; ?>

            <div class="resumo__total">
                <dt>Total</dt>
                <dd><?php wc_cart_totals_order_total_html(); ?></dd>
            </div>
        </dl>

        <a class="btn btn--azul btn--bloco" href="<?php echo esc_url(wc_get_checkout_url()); ?>">Ir para o pagamento</a>

        <p class="resumo__continuar">
            <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Continue comprando</a>
        </p>
    </div>
</section>

<?php do_action('woocommerce_after_cart'); ?>
