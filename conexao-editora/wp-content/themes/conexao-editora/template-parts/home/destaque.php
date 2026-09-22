<?php
/**
 * Carrossel de livros em destaque, com capa grande e compra direta.
 */

if (! defined('ABSPATH')) {
    exit;
}

$produtos = conexao_produtos('destaque', 6);

if (! $produtos) {
    return;
}
?>
<section class="destaque">
    <div class="destaque__trilho" data-arrastavel>
        <?php foreach ($produtos as $produto) : ?>
            <?php
            $categorias = get_the_terms($produto->get_id(), 'product_cat');
            $categoria = (! is_wp_error($categorias) && $categorias) ? $categorias[0] : null;
            ?>
            <article class="destaque__card">
                <a class="destaque__capa" href="<?php echo esc_url($produto->get_permalink()); ?>" tabindex="-1" aria-hidden="true">
                    <?php echo $produto->get_image('woocommerce_thumbnail'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </a>

                <div class="destaque__conteudo">
                    <div class="destaque__meta">
                        <?php if ($categoria) : ?>
                            <a class="etiqueta" href="<?php echo esc_url(get_term_link($categoria)); ?>"><?php echo esc_html($categoria->name); ?></a>
                        <?php endif; ?>
                        <?php conexao_estrelas($produto); ?>
                    </div>

                    <h3><a href="<?php echo esc_url($produto->get_permalink()); ?>"><?php echo esc_html($produto->get_name()); ?></a></h3>

                    <p class="destaque__resumo"><?php echo esc_html(wp_trim_words(wp_strip_all_tags($produto->get_short_description() ?: $produto->get_description()), 24)); ?></p>

                    <p class="destaque__preco"><?php echo wp_kses_post($produto->get_price_html()); ?></p>

                    <div class="destaque__acoes">
                        <?php if ($produto->is_purchasable() && $produto->is_in_stock()) : ?>
                            <a class="btn btn--azul" href="<?php echo esc_url($produto->add_to_cart_url()); ?>"
                               data-quantity="1" data-product_id="<?php echo esc_attr($produto->get_id()); ?>"
                               rel="nofollow">
                                <?php conexao_the_icon('carrinho', 20); ?> Adicionar no carrinho
                            </a>
                        <?php endif; ?>

                        <button class="botao-favorito" type="button" aria-label="Salvar nos favoritos"><?php conexao_the_icon('coracao', 20); ?></button>

                        <a class="destaque__detalhes" href="<?php echo esc_url($produto->get_permalink()); ?>">Ver detalhes</a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
