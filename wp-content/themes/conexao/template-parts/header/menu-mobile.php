<?php
/**
 * Menu lateral do mobile.
 *
 * Reúne o que some nas telas estreitas: a faixa de categorias, os links da
 * topbar e o botão de orçamento.
 */

defined( 'ABSPATH' ) || exit;

$cnx_orcamento = (string) get_option( 'cnx_orcamento_url', '' );

if ( '' === $cnx_orcamento && function_exists( 'cnx_whatsapp_link' ) ) {
	$cnx_orcamento = cnx_whatsapp_link( cnx_whatsapp_saudacao() );
}
?>

<?php
/*
 * Sem o atributo hidden: display:none não anima. O painel fica fora da tela por
 * visibility + transform, e o CSS é quem o esconde de leitores de tela e do foco.
 */
?>
<div class="cnx-menu-mobile" id="cnx-menu-mobile" data-cnx-menu
	style="background-image:url('<?php echo esc_url( get_theme_file_uri( 'assets/img/header-bg-mobile.png' ) ); ?>');">

	<div class="cnx-menu-mobile__topo">
		<button type="button" class="cnx-menu-mobile__fechar" data-cnx-menu-fechar
			aria-label="<?php esc_attr_e( 'Fechar menu', 'conexao' ); ?>">
			<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor"
				stroke-width="2.6" stroke-linecap="round" aria-hidden="true" focusable="false">
				<path d="M6 6 18 18M18 6 6 18"/>
			</svg>
		</button>
	</div>

	<nav class="cnx-menu-mobile__nav" aria-label="<?php esc_attr_e( 'Menu principal', 'conexao' ); ?>">
		<?php
		get_template_part(
			'template-parts/header/lista-categorias',
			null,
			array( 'classe' => 'cnx-menu-mobile__categorias' )
		);

		wp_nav_menu(
			array(
				'theme_location' => 'topo',
				'container'      => false,
				'menu_class'     => 'cnx-menu-mobile__institucional',
				'depth'          => 1,
				'fallback_cb'    => 'cnx_topbar_menu_fallback',
			)
		);
		?>

		<?php if ( '' !== $cnx_orcamento ) : ?>
			<a class="cnx-btn cnx-btn--primario cnx-btn--bloco" href="<?php echo esc_url( $cnx_orcamento ); ?>">
				<?php esc_html_e( 'Solicitar Orçamento', 'conexao' ); ?>
				<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor"
					stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
					<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
				</svg>
			</a>
		<?php endif; ?>
	</nav>
</div>
