<?php
/**
 * Card de post na listagem do blog.
 */

defined( 'ABSPATH' ) || exit;

$cnx_id    = get_the_ID();
$cnx_autor = get_the_author_meta( 'display_name' );
$cnx_cats  = get_the_category();
?>

<article <?php post_class( 'cnx-post-card' ); ?>>

	<?php if ( has_post_thumbnail() ) : ?>
		<a class="cnx-post-card__midia" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
			<?php the_post_thumbnail( 'cnx-produto', array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
		</a>
	<?php endif; ?>

	<div class="cnx-post-card__corpo">
		<div class="cnx-post-card__topo">
			<h2 class="cnx-post-card__titulo">
				<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
			</h2>

			<?php // Compartilhar: Web Share API no celular, cópia do link no desktop. ?>
			<button type="button" class="cnx-compartilhar" data-cnx-compartilhar
				data-url="<?php the_permalink(); ?>"
				data-titulo="<?php echo esc_attr( get_the_title() ); ?>"
				aria-label="<?php esc_attr_e( 'Compartilhar este post', 'conexao' ); ?>">
				<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
					stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
					<path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/>
				</svg>
			</button>
		</div>

		<p class="cnx-post-card__resumo"><?php echo esc_html( get_the_excerpt() ); ?></p>

		<footer class="cnx-post-card__meta">
			<span class="cnx-post-card__autor">
				<?php echo get_avatar( get_the_author_meta( 'ID' ), 26, '', '', array( 'class' => 'cnx-avatar' ) ); ?>
				<?php echo esc_html( $cnx_autor ); ?>
			</span>

			<span aria-hidden="true">·</span>
			<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>

			<span aria-hidden="true">·</span>
			<span>
				<?php
				printf(
					/* translators: %d: minutos de leitura */
					esc_html__( '%d min', 'conexao' ),
					cnx_tempo_leitura( $cnx_id )
				);
				?>
			</span>

			<?php if ( ! empty( $cnx_cats ) ) : ?>
				<span class="cnx-post-card__cats">
					<?php foreach ( array_slice( $cnx_cats, 0, 2 ) as $cnx_cat ) : ?>
						<a href="<?php echo esc_url( (string) get_category_link( $cnx_cat ) ); ?>">
							<?php echo esc_html( $cnx_cat->name ); ?>
						</a>
					<?php endforeach; ?>
				</span>
			<?php endif; ?>
		</footer>
	</div>

</article>
