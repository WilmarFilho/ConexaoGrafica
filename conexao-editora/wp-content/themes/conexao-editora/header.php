<?php
/**
 * Barra do topo, cabeçalho e menu principal.
 */

if (! defined('ABSPATH')) {
    exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="pular-para-conteudo" href="#conteudo">Ir para o conteúdo</a>

<div class="barra-topo">
    <div class="container barra-topo__grade">
        <p class="barra-topo__frete">
            <img src="<?php echo esc_url(get_template_directory_uri().'/assets/img/icone-frete.png'); ?>" alt="" width="27" height="20">
            Frete grátis acima de R$499
        </p>

        <?php
        if (has_nav_menu('topo')) {
            wp_nav_menu([
                'theme_location' => 'topo',
                'container' => 'nav',
                'container_class' => 'barra-topo__links',
                'menu_class' => 'menu-topo',
                'depth' => 1,
            ]);
        }
        ?>

        <a class="barra-topo__pedido" href="<?php echo esc_url(home_url('/acompanhe-seu-pedido/')); ?>">
            <img src="<?php echo esc_url(get_template_directory_uri().'/assets/img/icone-pedido.png'); ?>" alt="" width="16" height="21">
            Acompanhe seu pedido
        </a>
    </div>
</div>

<header class="cabecalho">
    <div class="container cabecalho__grade">
        <?php conexao_logo(); ?>

        <form class="busca" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
            <label class="tela-leitor" for="busca-topo">Buscar no catálogo</label>
            <input type="search" id="busca-topo" name="s" value="<?php echo esc_attr(get_search_query()); ?>"
                   placeholder="Busca por título, autor, ISBN ou palavra-chave">
            <?php if (class_exists('WooCommerce')) : ?>
                <input type="hidden" name="post_type" value="product">
            <?php endif; ?>
            <button type="submit" aria-label="Buscar"><?php conexao_the_icon('lupa', 20); ?></button>
        </form>

        <div class="cabecalho__acoes">
            <a class="hdr-acao" href="<?php echo esc_url(class_exists('WooCommerce') ? wc_get_page_permalink('myaccount') : home_url('/minha-conta/')); ?>">
                <span class="hdr-acao__icone"><?php conexao_the_icon('usuario', 22); ?></span>
                <span class="hdr-acao__texto">
                    <b>Minha conta</b>
                    <span><?php echo is_user_logged_in() ? 'Meus pedidos' : 'Entrar | Criar conta'; ?></span>
                </span>
            </a>

            <?php conexao_cart_button(); ?>

            <button class="menu-botao" type="button" aria-expanded="false" aria-controls="menu-principal">
                <?php conexao_the_icon('menu', 24); ?>
                <span class="tela-leitor">Abrir o menu</span>
            </button>
        </div>
    </div>
</header>

<nav class="menu-principal" id="menu-principal" aria-label="Menu principal">
    <div class="container">
        <?php
        wp_nav_menu([
            'theme_location' => 'principal',
            'container' => false,
            'menu_class' => 'menu',
            'depth' => 2,
            'fallback_cb' => false,
        ]);
        ?>
    </div>
</nav>

<main id="conteudo">
