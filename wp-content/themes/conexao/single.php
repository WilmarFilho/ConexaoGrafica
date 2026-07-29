<?php
/**
 * Post do blog.
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$cnx_id     = get_the_ID();
	$cnx_cats   = get_the_category();
	$cnx_blog   = (int) get_option( 'page_for_posts' );
	$cnx_trilha = array();

	if ( $cnx_blog ) {
		$cnx_trilha[] = array( get_the_title( $cnx_blog ), (string) get_permalink( $cnx_blog ) );
	}

	$cnx_trilha[] = array( get_the_title(), '' );
	?>

	<div class="cnx-secao__inner">
		<?php cnx_breadcrumb( $cnx_trilha ); ?>
	</div>

	<div class="cnx-blog">
		<div class="cnx-secao__inner cnx-blog__grade">

			<article <?php post_class( 'cnx-artigo' ); ?>>

				<h1 class="cnx-artigo__titulo"><?php the_title(); ?></h1>

				<?php if ( has_excerpt() ) : ?>
					<p class="cnx-artigo__linha-fina"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php endif; ?>

				<div class="cnx-artigo__meta">
					<span class="cnx-post-card__autor">
						<?php echo get_avatar( get_the_author_meta( 'ID' ), 26, '', '', array( 'class' => 'cnx-avatar' ) ); ?>
						<?php echo esc_html( get_the_author_meta( 'display_name' ) ); ?>
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

					<button type="button" class="cnx-compartilhar cnx-compartilhar--destaque" data-cnx-compartilhar
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

				<?php if ( has_post_thumbnail() ) : ?>
					<figure class="cnx-artigo__capa">
						<?php the_post_thumbnail( 'cnx-produto', array( 'fetchpriority' => 'high', 'alt' => get_the_title() ) ); ?>
					</figure>
				<?php endif; ?>

				<div class="cnx-artigo__corpo">
					<?php the_content(); ?>
				</div>

				<?php the_tags( '<p class="cnx-artigo__tags">', '', '</p>' ); ?>

			</article>

			<?php get_template_part( 'template-parts/blog/sidebar' ); ?>

		</div>
	</div>

	<?php
	/**
	 * "Fique por dentro": posts da mesma categoria. Sem categoria em comum,
	 * cai nos mais recentes — a seção nunca aparece vazia.
	 */
	$cnx_ids_cats = wp_list_pluck( $cnx_cats, 'term_id' );

	$cnx_relacionados = get_posts(
		array(
			'post_type'      => 'post',
			'posts_per_page' => 2,
			'post__not_in'   => array( $cnx_id ),
			'category__in'   => ! empty( $cnx_ids_cats ) ? $cnx_ids_cats : array(),
		)
	);

	if ( empty( $cnx_relacionados ) ) {
		$cnx_relacionados = get_posts(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 2,
				'post__not_in'   => array( $cnx_id ),
			)
		);
	}

	if ( ! empty( $cnx_relacionados ) ) :
		?>
		<section class="cnx-secao cnx-relacionados-blog">
			<div class="cnx-secao__inner">
				<h2 class="cnx-secao__titulo"><?php esc_html_e( 'Fique por dentro', 'conexao' ); ?></h2>

				<ul class="cnx-grade cnx-relacionados-blog__lista">
					<?php foreach ( $cnx_relacionados as $cnx_rel ) : ?>
						<li>
							<a class="cnx-mini-post" href="<?php echo esc_url( (string) get_permalink( $cnx_rel ) ); ?>">
								<?php if ( has_post_thumbnail( $cnx_rel ) ) : ?>
									<span class="cnx-mini-post__midia">
										<?php echo get_the_post_thumbnail( $cnx_rel, 'cnx-card', array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
									</span>
								<?php endif; ?>

								<span class="cnx-mini-post__corpo">
									<strong><?php echo esc_html( get_the_title( $cnx_rel ) ); ?></strong>
									<span><?php echo esc_html( wp_trim_words( (string) get_the_excerpt( $cnx_rel ), 18 ) ); ?></span>
									<em><?php esc_html_e( 'Ver mais', 'conexao' ); ?></em>
								</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
		<?php
	endif;

endwhile;

get_footer();
