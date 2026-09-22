<?php
/**
 * Endereço que não existe.
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();
?>
<div class="container pagina">
    <header class="pagina__topo">
        <h1>Esta página não existe</h1>
    </header>

    <p class="texto">O endereço pode ter mudado. Busque pelo título ou volte ao catálogo.</p>
    <?php get_search_form(); ?>

    <p><a class="btn btn--azul" href="<?php echo esc_url(class_exists('WooCommerce') ? wc_get_page_permalink('shop') : home_url('/')); ?>">Ver o catálogo</a></p>
</div>
<?php
get_footer();
