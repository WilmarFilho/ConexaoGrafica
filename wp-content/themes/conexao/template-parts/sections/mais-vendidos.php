<?php
/**
 * Carrossel de produtos. Vem de [cnx_mais_vendidos].
 *
 * Diferente do hero: aqui o trilho rola horizontalmente e mostra vários cards
 * por vez, então usa scroll nativo (com scroll-snap) em vez de translateX.
 *
 * @var array $args { @type string $titulo  @type WP_Post[] $produtos }
 */

defined( 'ABSPATH' ) || exit;

$cnx_titulo   = (string) ( $args['titulo'] ?? '' );
$cnx_produtos = $args['produtos'] ?? array();

if ( empty( $cnx_produtos ) ) {
	return;
}
?>

<section class="cnx-secao cnx-vitrine">
	<div class="cnx-secao__inner">

		<?php if ( '' !== $cnx_titulo ) : ?>
			<h2 class="cnx-secao__titulo"><?php echo esc_html( $cnx_titulo ); ?></h2>
		<?php endif; ?>

		<div class="cnx-palco" data-cnx-trilho-rolavel>
			<ul class="cnx-grade cnx-grade--trilho" data-cnx-pista>
				<?php foreach ( $cnx_produtos as $cnx_produto ) : ?>
					<?php $cnx_resumo = (string) cnx_meta( $cnx_produto->ID, 'resumo' ); ?>
					<li>
						<a class="cnx-card-produto" href="<?php echo esc_url( (string) get_permalink( $cnx_produto ) ); ?>">
							<span class="cnx-card-produto__midia">
								<?php cnx_figura( (int) get_post_thumbnail_id( $cnx_produto ), 'cnx-card' ); ?>
							</span>

							<span class="cnx-card-produto__texto">
								<strong><?php echo esc_html( get_the_title( $cnx_produto ) ); ?>:</strong>
								<?php if ( '' !== $cnx_resumo ) : ?>
									<span><?php echo esc_html( $cnx_resumo ); ?></span>
								<?php endif; ?>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php get_template_part( 'template-parts/sections/setas' ); ?>
		</div>

	</div>
</section>
