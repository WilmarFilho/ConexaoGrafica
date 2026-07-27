<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="cnx-pular" href="#conteudo"><?php esc_html_e( 'Pular para o conteúdo', 'conexao' ); ?></a>

<header class="cnx-header">
	<?php get_template_part( 'template-parts/header/topbar' ); ?>
	<?php get_template_part( 'template-parts/header/branding' ); ?>
	<?php get_template_part( 'template-parts/header/categorias' ); ?>
	<?php get_template_part( 'template-parts/header/menu-mobile' ); ?>
</header>

<main id="conteudo">
