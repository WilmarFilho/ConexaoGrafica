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
			<?php // Sem área de usuário nesta fase: o lugar é do cupom de 10% OFF do rodapé. ?>
			<a class="cnx-acao" href="#cnx-desconto"
				aria-label="<?php esc_attr_e( 'Cupom de 10% de desconto no primeiro pedido', 'conexao' ); ?>">
				<?php // Cupom com mordidas laterais e picote central, como a arte do Figma. ?>
				<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
					stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<path d="M5.2 6.8h13.6a1.8 1.8 0 0 1 1.8 1.8v1.2a2.7 2.7 0 0 0 0 4.9v0.7a1.8 1.8 0 0 1-1.8 1.8H5.2a1.8 1.8 0 0 1-1.8-1.8v-0.7a2.7 2.7 0 0 0 0-4.9V8.6a1.8 1.8 0 0 1 1.8-1.8Z"/>
					<path d="M12 10.4v3.2"/>
				</svg>
			</a>

			<a class="cnx-acao" href="<?php echo esc_url( home_url( '/orcamento/' ) ); ?>"
				aria-label="<?php esc_attr_e( 'Meu orçamento', 'conexao' ); ?>">
				<?php // Recibo com linhas e abas recortadas embaixo, como a arte do Figma. ?>
				<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
					stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<rect x="5.5" y="4.5" width="13" height="15.5" rx="2.4"/>
					<path d="M9.3 9.6h5.4M9.3 12.8h5.4"/>
					<path d="M9.6 20v-2.2M14.4 20v-2.2"/>
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
