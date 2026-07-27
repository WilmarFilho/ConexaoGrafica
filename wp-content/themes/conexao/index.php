<?php
/**
 * Fallback genérico. Usado pelo blog enquanto os templates específicos não existem.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="cnx-container">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			<article <?php post_class(); ?>>
				<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
				<?php the_excerpt(); ?>
			</article>
		<?php endwhile; ?>

		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Nada encontrado.', 'conexao' ); ?></p>
	<?php endif; ?>
</div>

<?php
get_footer();
