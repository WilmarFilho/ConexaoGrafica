<?php
/**
 * Banner de chamada com foto de fundo. Vem de [cnx_banner slug="..."].
 *
 * @var array $args { @type WP_Post $banner }
 */

defined( 'ABSPATH' ) || exit;

$cnx_banner = $args['banner'] ?? null;

if ( ! $cnx_banner instanceof WP_Post ) {
	return;
}

$cnx_id      = $cnx_banner->ID;
$cnx_titulo  = (string) cnx_meta( $cnx_id, 'banner_titulo' );
$cnx_texto   = (string) cnx_meta( $cnx_id, 'banner_texto' );
$cnx_btn_txt = (string) cnx_meta( $cnx_id, 'banner_btn_txt' );
$cnx_btn_url = (string) cnx_meta( $cnx_id, 'banner_btn_url' );
$cnx_fundo   = get_the_post_thumbnail_url( $cnx_id, 'full' );

if ( '' === trim( $cnx_titulo ) ) {
	$cnx_titulo = esc_html( get_the_title( $cnx_id ) );
}
?>

<section class="cnx-secao cnx-banner">
	<div class="cnx-secao__inner">
		<div class="cnx-banner__caixa <?php echo $cnx_fundo ? '' : 'cnx-banner__caixa--sem-foto'; ?>"
			<?php if ( $cnx_fundo ) : ?>
				style="background-image:url('<?php echo esc_url( $cnx_fundo ); ?>');"
			<?php endif; ?>>

			<div class="cnx-banner__conteudo">
				<div class="cnx-banner__texto">
					<h2 class="cnx-banner__titulo">
						<?php echo wp_kses( $cnx_titulo, cnx_slide_html_permitido() ); ?>
					</h2>

					<?php if ( '' !== $cnx_texto ) : ?>
						<p class="cnx-banner__descricao"><?php echo esc_html( $cnx_texto ); ?></p>
					<?php endif; ?>
				</div>

				<?php if ( '' !== $cnx_btn_txt && '' !== $cnx_btn_url ) : ?>
					<div class="cnx-banner__acao">
						<a class="cnx-btn cnx-btn--primario" href="<?php echo esc_url( $cnx_btn_url ); ?>">
							<?php echo esc_html( $cnx_btn_txt ); ?>
						</a>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="cnx-hero__arcoiris" aria-hidden="true"></div>
	</div>
</section>
