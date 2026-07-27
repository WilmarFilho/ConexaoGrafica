<?php
/**
 * Topbar — primeira faixa do header, presente em todas as páginas.
 *
 * Esquerda:  atalho direto para o WhatsApp do vendedor.
 * Direita:   menu institucional + CTA de orçamento.
 */

defined( 'ABSPATH' ) || exit;

// O plugin Conexão Core pode estar desativado: a topbar não pode quebrar o site.
$whatsapp = function_exists( 'cnx_whatsapp_link' )
	? cnx_whatsapp_link( cnx_whatsapp_saudacao() )
	: '';

// A CTA aponta para a página de orçamento; sem ela, cai no WhatsApp.
$orcamento = (string) get_option( 'cnx_orcamento_url', '' );

if ( '' === $orcamento ) {
	$orcamento = $whatsapp;
}
?>

<div class="cnx-topbar">
	<div class="cnx-topbar__inner">

		<?php if ( $whatsapp ) : ?>
			<a class="cnx-topbar__vendedor" href="<?php echo esc_url( $whatsapp ); ?>" target="_blank" rel="noopener">
				<span class="cnx-topbar__zap" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="15" height="15" focusable="false">
						<path fill="currentColor" d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm5.8 14.16c-.25.69-1.44 1.32-1.99 1.4-.53.08-1.19.11-1.92-.12-.44-.14-1.01-.33-1.74-.64-3.06-1.32-5.06-4.4-5.21-4.6-.15-.2-1.25-1.66-1.25-3.17s.79-2.25 1.07-2.56c.28-.31.61-.38.81-.38h.58c.19 0 .44-.07.69.53.25.6.86 2.08.94 2.23.08.15.13.33.02.53-.1.2-.15.33-.3.5l-.45.53c-.15.15-.31.32-.13.63.17.31.77 1.28 1.66 2.07 1.14 1.02 2.1 1.34 2.4 1.49.3.15.48.13.65-.08.18-.2.75-.88.95-1.18.2-.3.4-.25.68-.15.28.1 1.76.83 2.06.98.3.15.5.23.58.35.07.13.07.72-.18 1.41Z"/>
					</svg>
				</span>
				<span><?php esc_html_e( 'Comprar com Vendedor', 'conexao' ); ?></span>
			</a>
		<?php else : ?>
			<span></span>
		<?php endif; ?>

		<nav class="cnx-topbar__nav" aria-label="<?php esc_attr_e( 'Menu institucional', 'conexao' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'topo',
					'container'      => false,
					'menu_class'     => 'cnx-topbar__menu',
					'depth'          => 1,
					'fallback_cb'    => 'cnx_topbar_menu_fallback',
				)
			);
			?>

			<?php if ( $orcamento ) : ?>
				<a class="cnx-topbar__cta" href="<?php echo esc_url( $orcamento ); ?>"
					<?php echo $orcamento === $whatsapp ? 'target="_blank" rel="noopener"' : ''; ?>>
					<?php esc_html_e( 'Solicitar Orçamento', 'conexao' ); ?>
				</a>
			<?php endif; ?>
		</nav>

	</div>
</div>
