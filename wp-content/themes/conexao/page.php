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
		<?php the_content(); ?>
	</article>
	<?php
endwhile;

get_footer();
