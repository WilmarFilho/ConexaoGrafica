<?php
/**
 * Coluna lateral do blog: busca, categorias, mais lidos, banner, tags e redes.
 */

defined( 'ABSPATH' ) || exit;

$cnx_categorias = get_categories( array( 'hide_empty' => true, 'number' => 12 ) );

// "Mais lidos" vem do contador em _cnx_visualizacoes (ver cnx_contar_visualizacao).
$cnx_mais_lidos = get_posts(
	array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 3,
		'meta_key'       => '_cnx_visualizacoes',
		'orderby'        => 'meta_value_num',
		'order'          => 'DESC',
	)
);

// Sem nenhuma leitura registrada ainda, mostra os mais recentes.
if ( empty( $cnx_mais_lidos ) ) {
	$cnx_mais_lidos = get_posts( array( 'post_type' => 'post', 'posts_per_page' => 3 ) );
}

$cnx_banner = get_posts(
	array(
		'post_type'   => 'cnx_banner',
		'post_status' => 'publish',
		'numberposts' => 1,
		'name'        => 'blog-lateral',
	)
);

$cnx_redes = array_filter(
	array(
		'instagram' => (string) get_option( 'cnx_instagram', '' ),
		'facebook'  => (string) get_option( 'cnx_facebook', '' ),
		'youtube'   => (string) get_option( 'cnx_youtube', '' ),
	)
);
?>

<aside class="cnx-lateral" aria-label="<?php esc_attr_e( 'Conteúdo complementar', 'conexao' ); ?>">

	<section class="cnx-lateral__bloco">
		<h2 class="cnx-lateral__titulo"><?php esc_html_e( 'Busca', 'conexao' ); ?></h2>

		<form class="cnx-busca-lateral" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="screen-reader-text" for="cnx-busca-blog"><?php esc_html_e( 'Buscar no blog', 'conexao' ); ?></label>
			<input type="search" id="cnx-busca-blog" name="s" value="<?php echo esc_attr( get_search_query() ); ?>"
				placeholder="<?php esc_attr_e( 'Procure por post...', 'conexao' ); ?>">
			<?php // Restringe a busca ao blog: quem está aqui procura artigo, não produto. ?>
			<input type="hidden" name="post_type" value="post">

			<button type="submit" aria-label="<?php esc_attr_e( 'Buscar', 'conexao' ); ?>">
				<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
					stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false">
					<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
				</svg>
			</button>
		</form>
	</section>

	<?php if ( ! empty( $cnx_categorias ) ) : ?>
		<section class="cnx-lateral__bloco">
			<h2 class="cnx-lateral__titulo"><?php esc_html_e( 'Categorias', 'conexao' ); ?></h2>

			<ul class="cnx-etiquetas">
				<?php foreach ( $cnx_categorias as $cnx_cat ) : ?>
					<li>
						<a href="<?php echo esc_url( (string) get_category_link( $cnx_cat ) ); ?>">
							<?php echo esc_html( $cnx_cat->name ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $cnx_mais_lidos ) ) : ?>
		<section class="cnx-lateral__bloco">
			<h2 class="cnx-lateral__titulo"><?php esc_html_e( 'Posts mais lidos', 'conexao' ); ?></h2>

			<ul class="cnx-mais-lidos">
				<?php foreach ( $cnx_mais_lidos as $cnx_post ) : ?>
					<li>
						<a href="<?php echo esc_url( (string) get_permalink( $cnx_post ) ); ?>">
							<?php echo esc_html( get_the_title( $cnx_post ) ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $cnx_banner ) && has_post_thumbnail( $cnx_banner[0] ) ) : ?>
		<?php
		$cnx_bn      = $cnx_banner[0];
		$cnx_bn_url  = (string) cnx_meta( $cnx_bn->ID, 'banner_btn_url' );
		$cnx_bn_url  = '' !== $cnx_bn_url ? $cnx_bn_url : home_url( '/orcamento/' );
		?>
		<section class="cnx-lateral__bloco">
			<a class="cnx-lateral__banner" href="<?php echo esc_url( $cnx_bn_url ); ?>">
				<?php
				echo get_the_post_thumbnail(
					$cnx_bn,
					'cnx-card',
					array( 'alt' => get_the_title( $cnx_bn ), 'loading' => 'lazy' )
				);
				?>
			</a>
		</section>
	<?php endif; ?>

	<?php
	$cnx_tags = get_tags( array( 'hide_empty' => true, 'number' => 20 ) );

	if ( ! empty( $cnx_tags ) ) :
		// Peso pela quantidade de posts: o assunto recorrente aparece maior.
		$cnx_max = max( wp_list_pluck( $cnx_tags, 'count' ) );
		?>
		<section class="cnx-lateral__bloco">
			<h2 class="cnx-lateral__titulo"><?php esc_html_e( 'Nuvem de tags', 'conexao' ); ?></h2>

			<p class="cnx-nuvem">
				<?php foreach ( $cnx_tags as $cnx_tag ) : ?>
					<?php $cnx_peso = $cnx_max > 0 ? round( 12 + ( $cnx_tag->count / $cnx_max ) * 6 ) : 14; ?>
					<a href="<?php echo esc_url( (string) get_tag_link( $cnx_tag ) ); ?>"
						style="font-size:<?php echo esc_attr( (string) $cnx_peso ); ?>px;">
						<?php echo esc_html( $cnx_tag->name ); ?>
					</a>
				<?php endforeach; ?>
			</p>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $cnx_redes ) ) : ?>
		<section class="cnx-lateral__bloco">
			<h2 class="cnx-lateral__titulo"><?php esc_html_e( 'Siga-nos', 'conexao' ); ?></h2>

			<ul class="cnx-redes">
				<?php foreach ( $cnx_redes as $cnx_rede => $cnx_url ) : ?>
					<li>
						<a href="<?php echo esc_url( $cnx_url ); ?>" target="_blank" rel="noopener"
							aria-label="<?php echo esc_attr( ucfirst( $cnx_rede ) ); ?>">
							<?php echo cnx_icone_rede( $cnx_rede ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

</aside>
