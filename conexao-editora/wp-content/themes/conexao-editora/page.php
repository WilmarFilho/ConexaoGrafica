<?php
/**
 * Página comum. As páginas do WooCommerce (conta, carrinho, finalizar compra)
 * trocam o título pelo caminho curto, como no layout.
 */

if (! defined('ABSPATH')) {
    exit;
}

$pagina_loja = function_exists('is_account_page')
    && (is_account_page() || is_cart() || is_checkout());

get_header();

while (have_posts()) :
    the_post();
    ?>
    <article class="container pagina<?php echo $pagina_loja ? ' pagina--loja' : ''; ?>">
        <?php if ($pagina_loja) : ?>
            <?php conexao_trilha(); ?>
        <?php else : ?>
            <header class="pagina__topo">
                <h1><?php the_title(); ?></h1>
            </header>
        <?php endif; ?>

        <div class="<?php echo $pagina_loja ? 'conteudo-loja' : 'texto'; ?>">
            <?php the_content(); ?>
        </div>
    </article>
    <?php
endwhile;

get_footer();
