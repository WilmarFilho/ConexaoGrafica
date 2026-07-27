<?php
/**
 * Faixa branca do header: logo, busca e ações (conta e carrinho).
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="cnx-branding">
	<div class="cnx-branding__inner">

		<a class="cnx-branding__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"
			aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			<?php
			if ( has_custom_logo() ) {
				// Logo definida em Aparência → Personalizar tem prioridade.
				the_custom_logo();
			} else {
				printf(
					// Dimensões reais do arquivo: evitam salto de layout no carregamento.
					'<img src="%s" alt="%s" width="227" height="60" fetchpriority="high">',
					esc_url( get_theme_file_uri( 'assets/img/logo.png' ) ),
					esc_attr( get_bloginfo( 'name' ) )
				);
			}
			?>
		</a>

		<?php get_search_form(); ?>

		<div class="cnx-branding__acoes">
			<a class="cnx-acao" href="<?php echo esc_url( wp_login_url() ); ?>"
				aria-label="<?php esc_attr_e( 'Minha conta', 'conexao' ); ?>">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
					stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
					<circle cx="12" cy="7" r="4"/>
				</svg>
			</a>

			<a class="cnx-acao" href="<?php echo esc_url( home_url( '/orcamento/' ) ); ?>"
				aria-label="<?php esc_attr_e( 'Meu orçamento', 'conexao' ); ?>">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
					stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<circle cx="9" cy="21" r="1"/>
					<circle cx="20" cy="21" r="1"/>
					<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
				</svg>
			</a>

			<?php // Só existe no mobile; no desktop o CSS o esconde. ?>
			<button type="button" class="cnx-hamburguer" data-cnx-menu-abrir
				aria-controls="cnx-menu-mobile" aria-expanded="false"
				aria-label="<?php esc_attr_e( 'Abrir menu', 'conexao' ); ?>">
				<span aria-hidden="true"></span>
				<span aria-hidden="true"></span>
				<span aria-hidden="true"></span>
			</button>
		</div>

	</div>
</div>
