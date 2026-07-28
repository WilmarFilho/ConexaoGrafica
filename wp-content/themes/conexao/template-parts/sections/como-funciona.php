<?php
/**
 * As quatro etapas do processo. Vem de [cnx_como_funciona].
 *
 * @var array $args { @type string $titulo  @type array $etapas }
 */

defined( 'ABSPATH' ) || exit;

$cnx_titulo = (string) ( $args['titulo'] ?? '' );
$cnx_etapas = $args['etapas'] ?? array();

if ( empty( $cnx_etapas ) ) {
	return;
}

/**
 * Artes fornecidas pelo cliente, em assets/img/etapas/.
 * O mapeamento segue a ordem do processo: solicite, aprove, produzimos, entregamos.
 */
$cnx_icones = array(
	'documento'  => 'aa.png',
	'aprovado'   => 'bb.png',
	'impressora' => 'cc.png',
	'entrega'    => 'dd.png',
);
?>

<section class="cnx-secao cnx-etapas">
	<div class="cnx-secao__inner">

		<?php if ( '' !== $cnx_titulo ) : ?>
			<h2 class="cnx-secao__titulo cnx-secao__titulo--centro"><?php echo esc_html( $cnx_titulo ); ?></h2>
		<?php endif; ?>

		<ol class="cnx-etapas__lista">
			<?php foreach ( $cnx_etapas as $cnx_i => $cnx_etapa ) : ?>
				<?php if ( $cnx_i > 0 ) : ?>
					<li class="cnx-etapas__seta" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
							stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
							<path d="m9 18 6-6-6-6"/>
						</svg>
					</li>
				<?php endif; ?>

				<li class="cnx-etapa">
					<span class="cnx-etapa__circulo" aria-hidden="true">
						<?php $cnx_arquivo = $cnx_icones[ $cnx_etapa['icone'] ?? '' ] ?? ''; ?>
						<?php if ( '' !== $cnx_arquivo ) : ?>
							<img src="<?php echo esc_url( get_theme_file_uri( 'assets/img/etapas/' . $cnx_arquivo ) ); ?>"
								alt="" loading="lazy" decoding="async">
						<?php endif; ?>
					</span>

					<span class="cnx-etapa__titulo"><?php echo esc_html( $cnx_etapa['titulo'] ?? '' ); ?></span>
				</li>
			<?php endforeach; ?>
		</ol>

	</div>
</section>
