<?php
/**
 * "Você também pode gostar de": cinco livros da mesma categoria (ou os mais novos).
 * $args['produto']: o produto da página, ou null num livro só com DOI.
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('wc_get_products')) {
    return;
}

$produto = $args['produto'] ?? null;
$excluir = $produto ? [$produto->get_id()] : [];
$categoria = null;

if ($produto && function_exists('conexao_categoria_do_livro')) {
    [$categoria] = conexao_categoria_do_livro($produto->get_id());
}

$sugestoes = wc_get_products([
    'status' => 'publish',
    'limit' => 5,
    'exclude' => $excluir,
    'category' => $categoria ? [$categoria->slug] : [],
    'orderby' => 'rand',
]);

if (count($sugestoes) < 5) {
    $sugestoes = wc_get_products(['status' => 'publish', 'limit' => 5, 'exclude' => $excluir, 'orderby' => 'date']);
}

if (! $sugestoes) {
    return;
}
?>
<section class="produto-sugestoes">
    <?php conexao_secao_titulo('Você também pode gostar de', wc_get_page_permalink('shop')); ?>

    <div class="produto-sugestoes__grade">
        <?php foreach ($sugestoes as $sugestao) : ?>
            <?php $outros_autores = conexao_autores($sugestao); ?>
            <article class="card-catalogo card-catalogo--limpo">
                <a class="card-catalogo__capa" href="<?php echo esc_url($sugestao->get_permalink()); ?>" tabindex="-1" aria-hidden="true">
                    <?php echo $sugestao->get_image('woocommerce_thumbnail'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </a>

                <h3 class="card-catalogo__titulo"><a href="<?php echo esc_url($sugestao->get_permalink()); ?>"><?php echo esc_html($sugestao->get_name()); ?></a></h3>

                <?php if ($outros_autores) : ?>
                    <p class="card-catalogo__autores">Autores: <?php echo esc_html($outros_autores); ?></p>
                <?php endif; ?>

                <p class="card-catalogo__preco"><?php echo wp_kses_post($sugestao->get_price_html()); ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>
