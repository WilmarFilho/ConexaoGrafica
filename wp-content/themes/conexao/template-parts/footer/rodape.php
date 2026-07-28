<?php
/**
 * Rodapé institucional — segunda seção, presente em todas as páginas.
 *
 * Telefones, e-mail, horário e redes vêm de Configurações → Conexão: nada de
 * dado de contato cravado no template.
 */

defined( 'ABSPATH' ) || exit;

$cnx_horario  = (string) get_option( 'cnx_horario', '' );
$cnx_tel1     = (string) get_option( 'cnx_telefone_1', '' );
$cnx_tel2     = (string) get_option( 'cnx_telefone_2', '' );
$cnx_email    = (string) get_option( 'cnx_email_comercial', '' );
$cnx_sobre    = (string) get_option( 'cnx_sobre_curto', '' );

$cnx_redes = array_filter(
	array(
		'instagram' => (string) get_option( 'cnx_instagram', '' ),
		'facebook'  => (string) get_option( 'cnx_facebook', '' ),
		'youtube'   => (string) get_option( 'cnx_youtube', '' ),
	)
);

$cnx_icones_redes = array(
	'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1.2" fill="currentColor" stroke="none"/>',
	'facebook'  => '<path d="M14 8.5h2.5V5.5H14c-2 0-3.3 1.4-3.3 3.4v1.8H8.5v3h2.2V21h3v-7.3h2.3l.5-3h-2.8V9.4c0-.6.3-.9.8-.9Z" fill="currentColor" stroke="none"/>',
	'youtube'   => '<rect x="2.5" y="6" width="19" height="12" rx="3.5"/><path d="m10.5 9.5 5 2.5-5 2.5Z" fill="currentColor" stroke="none"/>',
);

/**
 * Só os dígitos, para montar o link tel:.
 */
$cnx_tel_link = static fn( string $tel ): string => (string) preg_replace( '/\D/', '', $tel );
?>

<div class="cnx-rodape">
	<div class="cnx-rodape__inner">

		<div class="cnx-rodape__marca">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				<img src="<?php echo esc_url( get_theme_file_uri( 'assets/img/logo-vertical.png' ) ); ?>"
					alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
					width="196" height="167" loading="lazy" decoding="async">
			</a>

			<?php if ( '' !== $cnx_sobre ) : ?>
				<p class="cnx-rodape__sobre"><?php echo esc_html( $cnx_sobre ); ?></p>
			<?php endif; ?>
		</div>

		<nav class="cnx-rodape__menu" aria-label="<?php esc_attr_e( 'Menu do rodapé', 'conexao' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'rodape',
					'container'      => false,
					'menu_class'     => 'cnx-rodape__lista',
					'depth'          => 1,
					'fallback_cb'    => 'cnx_topbar_menu_fallback',
				)
			);
			?>
		</nav>

		<div class="cnx-rodape__contato">
			<?php if ( '' !== $cnx_horario ) : ?>
				<h3 class="cnx-rodape__rotulo"><?php esc_html_e( 'Horário', 'conexao' ); ?></h3>
				<p class="cnx-rodape__valor"><?php echo esc_html( $cnx_horario ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $cnx_tel1 || '' !== $cnx_tel2 ) : ?>
				<h3 class="cnx-rodape__rotulo"><?php esc_html_e( 'Telefones:', 'conexao' ); ?></h3>
				<p class="cnx-rodape__valor">
					<?php if ( '' !== $cnx_tel1 ) : ?>
						<a href="tel:<?php echo esc_attr( $cnx_tel_link( $cnx_tel1 ) ); ?>"><?php echo esc_html( $cnx_tel1 ); ?></a>
					<?php endif; ?>
					<?php if ( '' !== $cnx_tel1 && '' !== $cnx_tel2 ) : ?>
						<span aria-hidden="true"> / </span>
					<?php endif; ?>
					<?php if ( '' !== $cnx_tel2 ) : ?>
						<a href="tel:<?php echo esc_attr( $cnx_tel_link( $cnx_tel2 ) ); ?>"><?php echo esc_html( $cnx_tel2 ); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<?php if ( '' !== $cnx_email ) : ?>
				<h3 class="cnx-rodape__rotulo"><?php esc_html_e( 'E-mail:', 'conexao' ); ?></h3>
				<p class="cnx-rodape__valor">
					<a href="mailto:<?php echo esc_attr( $cnx_email ); ?>"><?php echo esc_html( $cnx_email ); ?></a>
				</p>
			<?php endif; ?>

			<?php get_template_part( 'template-parts/footer/selo', null, array( 'classe' => 'cnx-rodape__selo--desktop' ) ); ?>
		</div>

		<?php
		/*
		 * Pagamentos e redes ficam em blocos irmãos: no desktop empilham na quarta
		 * coluna, no mobile o grid os separa (redes ao lado do menu, pagamentos
		 * numa faixa própria). Ver .cnx-rodape__extras no CSS.
		 */
		?>
		<div class="cnx-rodape__extras">
			<div class="cnx-rodape__pagamentos">
			<h3 class="cnx-rodape__rotulo"><?php esc_html_e( 'Formas de pagamento', 'conexao' ); ?></h3>

			<?php
			// Artes fornecidas pelo cliente; o alt descreve o meio de pagamento.
			$cnx_pagamentos = array(
				'a' => array( __( 'Cartão de crédito', 'conexao' ), 41, 33 ),
				'b' => array( __( 'Pix', 'conexao' ), 36, 36 ),
				'c' => array( __( 'Boleto', 'conexao' ), 42, 33 ),
			);
			?>

			<ul class="cnx-pagamentos" aria-label="<?php esc_attr_e( 'Formas de pagamento aceitas', 'conexao' ); ?>">
				<?php foreach ( $cnx_pagamentos as $cnx_arquivo => $cnx_dados ) : ?>
					<li>
						<img src="<?php echo esc_url( get_theme_file_uri( "assets/img/pagamento/{$cnx_arquivo}.png" ) ); ?>"
							alt="<?php echo esc_attr( $cnx_dados[0] ); ?>"
							width="<?php echo esc_attr( (string) $cnx_dados[1] ); ?>"
							height="<?php echo esc_attr( (string) $cnx_dados[2] ); ?>"
							loading="lazy" decoding="async">
					</li>
				<?php endforeach; ?>
			</ul>

			<?php // No mobile o selo aparece aqui, sob os ícones. ?>
			<?php get_template_part( 'template-parts/footer/selo', null, array( 'classe' => 'cnx-rodape__selo--mobile' ) ); ?>
			</div>

			<?php if ( ! empty( $cnx_redes ) ) : ?>
				<div class="cnx-rodape__redes">
				<h3 class="cnx-rodape__rotulo"><?php esc_html_e( 'Conecte-se', 'conexao' ); ?></h3>

				<ul class="cnx-redes">
					<?php foreach ( $cnx_redes as $cnx_rede => $cnx_url ) : ?>
						<li>
							<a href="<?php echo esc_url( $cnx_url ); ?>" target="_blank" rel="noopener"
								aria-label="<?php echo esc_attr( ucfirst( $cnx_rede ) ); ?>">
								<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor"
									stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
									<?php
									// Paths literais do array acima.
									echo $cnx_icones_redes[ $cnx_rede ] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									?>
								</svg>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
				</div>
			<?php endif; ?>

			<?php
			// No fluxo do rodapé, não fixos: acompanham a borda do conteúdo e
			// não cobrem nada. No mobile a cópia da base assume.
			get_template_part( 'template-parts/footer/botoes', null, array( 'classe' => 'cnx-rodape__botoes--desktop' ) );
			?>
		</div>

	</div>

	<div class="cnx-rodape__base">
		<div class="cnx-rodape__base-inner">
			<?php // Só visível no mobile, ao lado dos links legais. ?>
			<?php get_template_part( 'template-parts/footer/botoes', null, array( 'classe' => 'cnx-rodape__botoes--mobile' ) ); ?>

			<p class="cnx-rodape__direitos">
				<?php
				printf(
					/* translators: 1: nome do site, 2: ano */
					esc_html__( '%1$s © %2$s. Todos os direitos reservados.', 'conexao' ),
					esc_html( get_bloginfo( 'name' ) ),
					esc_html( wp_date( 'Y' ) )
				);
				?>
			</p>

			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'legal',
					'container'      => 'nav',
					'menu_class'     => 'cnx-rodape__legal',
					'depth'          => 1,
					'fallback_cb'    => 'cnx_legal_menu_fallback',
				)
			);
			?>
		</div>
	</div>

	<div class="cnx-hero__arcoiris" aria-hidden="true"></div>
</div>
