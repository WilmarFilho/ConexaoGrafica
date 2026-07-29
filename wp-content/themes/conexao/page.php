<?php
/**
 * Página estática.
 *
 * O conteúdo sai sem container: páginas montadas com shortcodes de seção
 * precisam que cada seção controle a própria largura. Texto solto continua
 * limitado pela regra .cnx-pagina do style.css.
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'cnx-pagina' ); ?>>
		<?php if ( is_page( 'contato' ) ) : ?>
			<?php // No contato o design usa a trilha; o H1 é o da própria seção. ?>
			<div class="cnx-secao__inner">
				<?php cnx_breadcrumb( array( array( get_the_title(), '' ) ) ); ?>
			</div>
		<?php elseif ( ! is_front_page() ) : ?>
			<h1 class="cnx-pagina__titulo"><?php the_title(); ?></h1>
		<?php endif; ?>

		<?php the_content(); ?>
	</article>
	<?php
endwhile;

get_footer();
