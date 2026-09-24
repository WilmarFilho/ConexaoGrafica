<?php
/**
 * Cartão de livro do catálogo: capa, título, autores, preço e "ver mais".
 */

if (! defined('ABSPATH')) {
    exit;
}

global $product;

if (! $product instanceof WC_Product) {
    $product = wc_get_product(get_the_ID());
}

if (! $product) {
    return;
}

$autores = conexao_autores($product);
?>
<article class="card-catalogo">
    <a class="card-catalogo__capa" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
        <?php echo $product->get_image('woocommerce_thumbnail'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    </a>

    <h3 class="card-catalogo__titulo"><a href="<?php the_permalink(); ?>"><?php echo esc_html($product->get_name()); ?></a></h3>

    <?php if ($autores) : ?>
        <p class="card-catalogo__autores">Autores: <?php echo esc_html($autores); ?></p>
    <?php endif; ?>

    <p class="card-catalogo__preco"><?php echo wp_kses_post($product->get_price_html()); ?></p>

    <a class="btn btn--contorno btn--bloco btn--pequeno" href="<?php the_permalink(); ?>">Ver mais</a>
</article>
