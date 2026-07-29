<?php
/**
 * Soluções por segmento. Vem de [cnx_solucoes].
 *
 * @var array $args { @type string $titulo  @type WP_Term[] $termos }
 */

defined( 'ABSPATH' ) || exit;

$cnx_titulo = (string) ( $args['titulo'] ?? '' );
$cnx_termos = $args['termos'] ?? array();

if ( empty( $cnx_termos ) ) {
	return;
}
?>

<section class="cnx-secao cnx-solucoes">
	<div class="cnx-secao__inner">

		<?php if ( '' !== $cnx_titulo ) : ?>
			<h2 class="cnx-secao__titulo"><?php echo esc_html( $cnx_titulo ); ?></h2>
		<?php endif; ?>

		<div class="cnx-palco" data-cnx-trilho-rolavel>
		<ul class="cnx-grade cnx-grade--4" data-cnx-pista>
			<?php foreach ( $cnx_termos as $cnx_termo ) : ?>
				<?php
				$cnx_cor    = (string) get_term_meta( $cnx_termo->term_id, 'cnx_cor', true );
				$cnx_rotulo = (string) get_term_meta( $cnx_termo->term_id, 'cnx_rotulo', true );
				$cnx_img    = (int) get_term_meta( $cnx_termo->term_id, 'cnx_imagem', true );
				$cnx_fundo  = (string) get_term_meta( $cnx_termo->term_id, 'cnx_fundo', true );

				if ( '' === $cnx_cor ) {
					$cnx_cor = '#ff6700';
				}

				if ( '' === trim( wp_strip_all_tags( $cnx_rotulo ) ) ) {
					$cnx_rotulo = esc_html( $cnx_termo->name );
				}
				?>
				<li>
					<a class="cnx-card-solucao" href="<?php echo esc_url( (string) get_term_link( $cnx_termo ) ); ?>">
						<span class="cnx-card-solucao__tarja" style="background:<?php echo esc_attr( $cnx_cor ); ?>;">
							<?php echo wp_kses( $cnx_rotulo, cnx_slide_html_permitido() ); ?>
						</span>

						<span class="cnx-card-solucao__midia"
							<?php if ( '' !== $cnx_fundo ) : ?>style="background:<?php echo esc_attr( $cnx_fundo ); ?>;"<?php endif; ?>>
							<?php cnx_figura( $cnx_img, 'cnx-card', '', $cnx_termo->name ); ?>
						</span>

						<span class="cnx-card-solucao__texto">
							<strong><?php echo esc_html( $cnx_termo->name ); ?>:</strong>
							<?php if ( '' !== $cnx_termo->description ) : ?>
								<span><?php echo esc_html( $cnx_termo->description ); ?></span>
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
