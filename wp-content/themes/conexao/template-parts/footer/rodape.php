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
$cnx_whatsapp = function_exists( 'cnx_whatsapp_link' ) ? cnx_whatsapp_link( cnx_whatsapp_saudacao() ) : '';

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

			<p class="cnx-rodape__selo">
				<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
					stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<rect x="4" y="10" width="16" height="11" rx="2"/>
					<path d="M8 10V7a4 4 0 0 1 8 0v3"/>
				</svg>
				<?php esc_html_e( 'COMPRA SEGURA', 'conexao' ); ?>
			</p>
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

			<ul class="cnx-pagamentos" aria-label="<?php esc_attr_e( 'Formas de pagamento aceitas', 'conexao' ); ?>">
				<li title="<?php esc_attr_e( 'Cartão de crédito', 'conexao' ); ?>">
					<svg viewBox="0 0 32 22" width="30" height="21" fill="none" stroke="currentColor" stroke-width="1.4" focusable="false" aria-hidden="true">
						<rect x="1" y="1" width="30" height="20" rx="3"/><path d="M1 7.5h30"/>
					</svg>
				</li>
				<li title="<?php esc_attr_e( 'Pix', 'conexao' ); ?>">
					<svg viewBox="0 0 32 22" width="30" height="21" fill="none" stroke="currentColor" stroke-width="1.4" focusable="false" aria-hidden="true">
						<rect x="1" y="1" width="30" height="20" rx="3"/>
						<path d="m16 5.5 5.5 5.5L16 16.5 10.5 11Z"/>
					</svg>
				</li>
				<li title="<?php esc_attr_e( 'Boleto', 'conexao' ); ?>">
					<svg viewBox="0 0 32 22" width="30" height="21" fill="none" stroke="currentColor" stroke-width="1.4" focusable="false" aria-hidden="true">
						<rect x="1" y="1" width="30" height="20" rx="3"/>
						<path d="M7 6v10M10 6v10M13.5 6v10M17 6v10M20.5 6v10M24 6v10"/>
					</svg>
				</li>
			</ul>
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
		</div>

	</div>

	<div class="cnx-rodape__base">
		<div class="cnx-rodape__base-inner">
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

<?php if ( '' !== $cnx_whatsapp ) : ?>
	<a class="cnx-flutuante cnx-flutuante--zap" href="<?php echo esc_url( $cnx_whatsapp ); ?>"
		target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Falar no WhatsApp', 'conexao' ); ?>">
		<svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true" focusable="false">
			<path fill="currentColor" d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm5.8 14.16c-.25.69-1.44 1.32-1.99 1.4-.53.08-1.19.11-1.92-.12-.44-.14-1.01-.33-1.74-.64-3.06-1.32-5.06-4.4-5.21-4.6-.15-.2-1.25-1.66-1.25-3.17s.79-2.25 1.07-2.56c.28-.31.61-.38.81-.38h.58c.19 0 .44-.07.69.53.25.6.86 2.08.94 2.23.08.15.13.33.02.53-.1.2-.15.33-.3.5l-.45.53c-.15.15-.31.32-.13.63.17.31.77 1.28 1.66 2.07 1.14 1.02 2.1 1.34 2.4 1.49.3.15.48.13.65-.08.18-.2.75-.88.95-1.18.2-.3.4-.25.68-.15.28.1 1.76.83 2.06.98.3.15.5.23.58.35.07.13.07.72-.18 1.41Z"/>
		</svg>
	</a>
<?php endif; ?>

<button type="button" class="cnx-flutuante cnx-flutuante--topo" data-cnx-topo
	aria-label="<?php esc_attr_e( 'Voltar ao topo', 'conexao' ); ?>" hidden>
	<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
		stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
		<path d="m6 15 6-6 6 6"/>
	</svg>
</button>
