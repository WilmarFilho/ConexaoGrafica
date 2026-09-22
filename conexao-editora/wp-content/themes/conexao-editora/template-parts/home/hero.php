<?php
/**
 * Faixa de abertura: chamada, botão e capas em destaque.
 */

if (! defined('ABSPATH')) {
    exit;
}

$destaques = conexao_produtos('destaque', 8);
$titulo = get_theme_mod('conexao_hero_titulo', 'Grandes histórias');
$titulo_forte = get_theme_mod('conexao_hero_titulo_forte', 'conectam pessoas.');
$texto = get_theme_mod(
    'conexao_hero_texto',
    'Descubra livros que inspiram conhecimento, despertam novas ideias e aproximam leitores de histórias que fazem a diferença.'
);
?>
<section class="hero">
    <div class="container hero__grade">
        <div class="hero__texto">
            <h1><?php echo esc_html($titulo); ?><br><strong><?php echo esc_html($titulo_forte); ?></strong></h1>
            <p><?php echo esc_html($texto); ?></p>
            <a class="btn btn--branco" href="<?php echo esc_url(class_exists('WooCommerce') ? wc_get_page_permalink('shop') : home_url('/livros/')); ?>">Explorar livros</a>
        </div>

        <?php if ($destaques) : ?>
            <div class="hero__capas" data-carrossel="hero">
                <div class="hero__trilho" data-carrossel-trilho>
                    <?php foreach ($destaques as $produto) : ?>
                        <a class="hero__capa" href="<?php echo esc_url($produto->get_permalink()); ?>">
                            <?php echo $produto->get_image('woocommerce_thumbnail'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                            <span class="tela-leitor"><?php echo esc_html($produto->get_name()); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="hero__pontos" data-carrossel-pontos></div>
            </div>
        <?php endif; ?>
    </div>
</section>
