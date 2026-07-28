<?php
/**
 * Faixa escura de captura — primeira seção do rodapé, presente em todas as páginas.
 */

defined( 'ABSPATH' ) || exit;

$cnx_status    = isset( $_GET['cnx_lead'] ) ? sanitize_key( wp_unslash( $_GET['cnx_lead'] ) ) : '';
$cnx_orcamento = (string) get_option( 'cnx_orcamento_url', '' );

if ( '' === $cnx_orcamento && function_exists( 'cnx_whatsapp_link' ) ) {
	$cnx_orcamento = cnx_whatsapp_link( cnx_whatsapp_saudacao() );
}

$cnx_tipos = function_exists( 'cnx_tipos_de_servico' ) ? cnx_tipos_de_servico() : array();
?>

<?php // O fundo fica no CSS: o mobile troca a imagem por media query, e inline venceria. ?>
<section class="cnx-cta" id="cnx-desconto">
	<?php // Decorativo e fora do grid: ancorado no canto superior esquerdo da faixa. ?>
	<img class="cnx-cta__selo"
		src="<?php echo esc_url( get_theme_file_uri( 'assets/img/footer-selo.png' ) ); ?>"
		alt="" width="245" height="245" loading="lazy" decoding="async" aria-hidden="true">

	<div class="cnx-cta__inner">

		<div class="cnx-cta__chamada">
			<div class="cnx-cta__texto">
				<h2 class="cnx-cta__titulo"><?php esc_html_e( 'Aproveite um benefício exclusivo', 'conexao' ); ?></h2>

				<p class="cnx-cta__subtitulo">
					<?php
					printf(
						/* translators: %s: percentual de desconto em destaque */
						esc_html__( 'Ganhe %s no seu primeiro pedido.', 'conexao' ),
						'<strong>' . esc_html__( '10% OFF', 'conexao' ) . '</strong>'
					);
					?>
				</p>

				<?php if ( '' !== $cnx_orcamento ) : ?>
					<a class="cnx-btn cnx-btn--contorno" href="<?php echo esc_url( $cnx_orcamento ); ?>">
						<?php esc_html_e( 'Solicitar Orçamento', 'conexao' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<div class="cnx-cta__formulario">
			<?php if ( 'ok' === $cnx_status ) : ?>
				<p class="cnx-aviso cnx-aviso--sucesso" role="status">
					<?php esc_html_e( 'Pronto! Em breve entramos em contato com o seu desconto.', 'conexao' ); ?>
				</p>
			<?php else : ?>

				<?php if ( 'email' === $cnx_status ) : ?>
					<p class="cnx-aviso cnx-aviso--erro" role="alert">
						<?php esc_html_e( 'Confira o e-mail digitado e tente de novo.', 'conexao' ); ?>
					</p>
				<?php elseif ( 'erro' === $cnx_status ) : ?>
					<p class="cnx-aviso cnx-aviso--erro" role="alert">
						<?php esc_html_e( 'Não foi possível registrar agora. Tente novamente em instantes.', 'conexao' ); ?>
					</p>
				<?php endif; ?>

				<form class="cnx-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="cnx_lead">
					<?php wp_nonce_field( 'cnx_lead', 'cnx_lead_nonce' ); ?>

					<label class="screen-reader-text" for="cnx_email"><?php esc_html_e( 'E-mail', 'conexao' ); ?></label>
					<input type="email" id="cnx_email" name="cnx_email" class="cnx-form__campo"
						placeholder="<?php esc_attr_e( 'E-mail', 'conexao' ); ?>" required>

					<label class="screen-reader-text" for="cnx_tipo"><?php esc_html_e( 'Tipo de serviço', 'conexao' ); ?></label>
					<select id="cnx_tipo" name="cnx_tipo" class="cnx-form__campo cnx-form__campo--select">
						<option value=""><?php esc_html_e( 'Tipo de serviço', 'conexao' ); ?></option>
						<?php foreach ( $cnx_tipos as $cnx_tipo ) : ?>
							<option value="<?php echo esc_attr( $cnx_tipo ); ?>"><?php echo esc_html( $cnx_tipo ); ?></option>
						<?php endforeach; ?>
					</select>

					<?php // Armadilha para robôs: escondida no CSS, nunca preenchida por gente. ?>
					<div class="cnx-form__armadilha" aria-hidden="true">
						<label for="cnx_site"><?php esc_html_e( 'Deixe este campo em branco', 'conexao' ); ?></label>
						<input type="text" id="cnx_site" name="cnx_site" tabindex="-1" autocomplete="off">
					</div>

					<button type="submit" class="cnx-form__enviar">
						<?php esc_html_e( 'Quero o meu desconto', 'conexao' ); ?>
					</button>
				</form>
			<?php endif; ?>

			<p class="cnx-cta__nota">
				<?php esc_html_e( 'Válido para o primeiro pedido. Consulte as condições da campanha.', 'conexao' ); ?>
			</p>
		</div>

	</div>
</section>
