<?php
/**
 * Carrossel da home. Recebe os slides prontos do shortcode [cnx_hero].
 *
 * @var array $args {
 *     @type WP_Post[] $slides
 *     @type int       $autoplay  Segundos entre slides; 0 desliga.
 * }
 */

defined( 'ABSPATH' ) || exit;

$cnx_slides   = $args['slides'] ?? array();
$cnx_autoplay = (int) ( $args['autoplay'] ?? 0 );
$cnx_total    = count( $cnx_slides );

if ( 0 === $cnx_total ) {
	return;
}

$cnx_fundo_padrao = get_theme_file_uri( 'assets/img/hero-bg.png' );
?>

<section class="cnx-hero"
	data-cnx-carrossel
	data-autoplay="<?php echo esc_attr( (string) $cnx_autoplay ); ?>"
	aria-roledescription="<?php esc_attr_e( 'carrossel', 'conexao' ); ?>"
	aria-label="<?php esc_attr_e( 'Destaques', 'conexao' ); ?>">

	<div class="cnx-hero__janela">
		<div class="cnx-hero__trilho" data-cnx-trilho>

			<?php foreach ( $cnx_slides as $cnx_i => $cnx_slide ) : ?>
				<?php
				$cnx_id     = $cnx_slide->ID;
				$cnx_bg_id  = (int) cnx_meta( $cnx_id, 'slide_bg', 0 );
				$cnx_bg     = $cnx_bg_id ? wp_get_attachment_image_url( $cnx_bg_id, 'full' ) : $cnx_fundo_padrao;
				$cnx_titulo = (string) cnx_meta( $cnx_id, 'slide_titulo' );
				$cnx_texto  = (string) cnx_meta( $cnx_id, 'slide_texto' );
				$cnx_b1_txt = (string) cnx_meta( $cnx_id, 'slide_btn1_txt' );
				$cnx_b1_url = (string) cnx_meta( $cnx_id, 'slide_btn1_url' );
				$cnx_b2_txt = (string) cnx_meta( $cnx_id, 'slide_btn2_txt' );
				$cnx_b2_url = (string) cnx_meta( $cnx_id, 'slide_btn2_url' );

				// Sem título exibido, cai no título interno do slide.
				if ( '' === trim( $cnx_titulo ) ) {
					$cnx_titulo = esc_html( get_the_title( $cnx_id ) );
				}
				?>

				<div class="cnx-hero__slide"
					data-cnx-slide
					role="group"
					aria-roledescription="<?php esc_attr_e( 'slide', 'conexao' ); ?>"
					aria-label="<?php echo esc_attr( sprintf( __( '%1$d de %2$d', 'conexao' ), $cnx_i + 1, $cnx_total ) ); ?>"
					<?php echo $cnx_i > 0 ? 'aria-hidden="true"' : ''; ?>
					style="background-image:url('<?php echo esc_url( (string) $cnx_bg ); ?>');">

					<div class="cnx-hero__conteudo">

						<div class="cnx-hero__texto">
							<h2 class="cnx-hero__titulo">
								<?php echo wp_kses( $cnx_titulo, cnx_slide_html_permitido() ); ?>
							</h2>

							<?php if ( '' !== $cnx_texto ) : ?>
								<p class="cnx-hero__descricao"><?php echo esc_html( $cnx_texto ); ?></p>
							<?php endif; ?>

							<?php if ( ( $cnx_b1_txt && $cnx_b1_url ) || ( $cnx_b2_txt && $cnx_b2_url ) ) : ?>
								<div class="cnx-hero__botoes">
									<?php if ( $cnx_b1_txt && $cnx_b1_url ) : ?>
										<a class="cnx-btn cnx-btn--primario" href="<?php echo esc_url( $cnx_b1_url ); ?>">
											<?php echo esc_html( $cnx_b1_txt ); ?>
											<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor"
												stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
												<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
												<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
											</svg>
										</a>
									<?php endif; ?>

									<?php if ( $cnx_b2_txt && $cnx_b2_url ) : ?>
										<a class="cnx-btn cnx-btn--secundario" href="<?php echo esc_url( $cnx_b2_url ); ?>">
											<?php echo esc_html( $cnx_b2_txt ); ?>
											<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor"
												stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
												<path d="M4 12h15"/><path d="m13 6 6 6-6 6"/>
											</svg>
										</a>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</div>

						<?php if ( has_post_thumbnail( $cnx_id ) ) : ?>
							<div class="cnx-hero__imagem">
								<?php
								echo get_the_post_thumbnail(
									$cnx_id,
									'large',
									array(
										'class'    => 'cnx-hero__produto',
										// O primeiro slide é LCP; os outros podem esperar.
										'loading'  => 0 === $cnx_i ? 'eager' : 'lazy',
										'decoding' => 'async',
										'alt'      => '',
									)
								);
								?>
							</div>
						<?php endif; ?>

					</div>
				</div>
			<?php endforeach; ?>

		</div>

		<?php if ( $cnx_total > 1 ) : ?>
			<button type="button" class="cnx-hero__seta cnx-hero__seta--anterior" data-cnx-anterior
				aria-label="<?php esc_attr_e( 'Slide anterior', 'conexao' ); ?>">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
					stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<path d="m15 18-6-6 6-6"/>
				</svg>
			</button>

			<button type="button" class="cnx-hero__seta cnx-hero__seta--proximo" data-cnx-proximo
				aria-label="<?php esc_attr_e( 'Próximo slide', 'conexao' ); ?>">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
					stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<path d="m9 18 6-6-6-6"/>
				</svg>
			</button>

			<div class="cnx-hero__pontos" role="tablist" aria-label="<?php esc_attr_e( 'Escolher slide', 'conexao' ); ?>">
				<?php foreach ( $cnx_slides as $cnx_i => $cnx_slide ) : ?>
					<button type="button" class="cnx-hero__ponto" role="tab" data-cnx-ponto="<?php echo esc_attr( (string) $cnx_i ); ?>"
						aria-selected="<?php echo 0 === $cnx_i ? 'true' : 'false'; ?>"
						aria-label="<?php echo esc_attr( sprintf( __( 'Ir para o slide %d', 'conexao' ), $cnx_i + 1 ) ); ?>"></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="cnx-hero__arcoiris" aria-hidden="true"></div>
</section>
