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

$cnx_icones = array(
	'documento'  => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5"/><path d="M9 13h6"/><path d="M9 17h4"/>',
	'aprovado'   => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
	'impressora' => '<path d="M6 9V4h12v5"/><rect x="3" y="9" width="18" height="7" rx="2"/><path d="M6 14h12v6H6z"/>',
	'entrega'    => '<path d="M3 5h9v9H3z"/><path d="M12 8h4l3 3v3h-7"/><circle cx="7" cy="18" r="1.7"/><circle cx="16" cy="18" r="1.7"/>',
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
						<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor"
							stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" focusable="false">
							<?php
							// Paths literais do array acima, não entrada de usuário.
							echo $cnx_icones[ $cnx_etapa['icone'] ?? '' ] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</svg>
					</span>

					<span class="cnx-etapa__titulo"><?php echo esc_html( $cnx_etapa['titulo'] ?? '' ); ?></span>
				</li>
			<?php endforeach; ?>
		</ol>

	</div>
</section>
