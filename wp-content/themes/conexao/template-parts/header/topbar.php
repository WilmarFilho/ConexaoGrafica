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
				<img class="cnx-topbar__zap"
					src="<?php echo esc_url( get_theme_file_uri( 'assets/img/wpp.png' ) ); ?>"
					alt="" width="21" height="23" aria-hidden="true">
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
