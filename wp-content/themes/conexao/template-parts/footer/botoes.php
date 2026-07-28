<?php
/**
 * Botões de WhatsApp e voltar ao topo.
 *
 * Como o selo, aparecem em pontos diferentes por largura: no desktop na coluna
 * de extras, no mobile na base ao lado dos links legais. display:none esconde
 * a cópia inativa também dos leitores de tela.
 *
 * @var array $args { @type string $classe }
 */

defined( 'ABSPATH' ) || exit;

$cnx_whatsapp = function_exists( 'cnx_whatsapp_link' ) ? cnx_whatsapp_link( cnx_whatsapp_saudacao() ) : '';
?>

<div class="cnx-rodape__botoes <?php echo esc_attr( (string) ( $args['classe'] ?? '' ) ); ?>">
	<?php if ( '' !== $cnx_whatsapp ) : ?>
		<a class="cnx-botao-redondo cnx-botao-redondo--zap" href="<?php echo esc_url( $cnx_whatsapp ); ?>"
			target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Falar no WhatsApp', 'conexao' ); ?>">
			<svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true" focusable="false">
				<path fill="currentColor" d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm5.8 14.16c-.25.69-1.44 1.32-1.99 1.4-.53.08-1.19.11-1.92-.12-.44-.14-1.01-.33-1.74-.64-3.06-1.32-5.06-4.4-5.21-4.6-.15-.2-1.25-1.66-1.25-3.17s.79-2.25 1.07-2.56c.28-.31.61-.38.81-.38h.58c.19 0 .44-.07.69.53.25.6.86 2.08.94 2.23.08.15.13.33.02.53-.1.2-.15.33-.3.5l-.45.53c-.15.15-.31.32-.13.63.17.31.77 1.28 1.66 2.07 1.14 1.02 2.1 1.34 2.4 1.49.3.15.48.13.65-.08.18-.2.75-.88.95-1.18.2-.3.4-.25.68-.15.28.1 1.76.83 2.06.98.3.15.5.23.58.35.07.13.07.72-.18 1.41Z"/>
			</svg>
		</a>
	<?php endif; ?>

	<button type="button" class="cnx-botao-redondo cnx-botao-redondo--topo" data-cnx-topo
		aria-label="<?php esc_attr_e( 'Voltar ao topo', 'conexao' ); ?>">
		<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
			stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
			<path d="m6 15 6-6 6 6"/>
		</svg>
	</button>
</div>
