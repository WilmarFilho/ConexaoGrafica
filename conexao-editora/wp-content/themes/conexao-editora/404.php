<?php
/**
 * Endereço que não existe.
 */

if (! defined('ABSPATH')) {
    exit;
}

$loja = class_exists('WooCommerce') ? wc_get_page_permalink('shop') : home_url('/');
$arte = get_template_directory().'/assets/img/erro-404.png';

get_header();
?>
<div class="container pagina erro404">
    <?php conexao_trilha('Erro 404'); ?>

    <div class="erro404__grade">
        <div class="erro404__texto">
            <p class="erro404__numero">404</p>
            <h1>Página não encontrada</h1>
            <p class="erro404__resumo">Opa! A página que você tentou acessar não existe, foi removida ou está temporariamente indisponível.</p>

            <div class="erro404__acoes">
                <a class="btn btn--contorno" href="<?php echo esc_url($loja); ?>">Ver catálogo</a>
                <a class="btn btn--azul" href="<?php echo esc_url(home_url('/')); ?>">Voltar para Home</a>
            </div>

            <?php get_search_form(); ?>
        </div>

        <?php if (file_exists($arte)) : ?>
            <figure class="erro404__arte">
                <img src="<?php echo esc_url(get_template_directory_uri().'/assets/img/erro-404.png'); ?>"
                     alt="" aria-hidden="true" width="730" height="548" decoding="async">
            </figure>
        <?php endif; ?>
    </div>
</div>
<?php
get_footer();
