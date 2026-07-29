<?php
/**
 * Página de contato: canais + formulário à esquerda, mapa à direita.
 *
 * Vem de [cnx_contato]. Todos os dados saem de Configurações → Conexão.
 *
 * @var array $args { whatsapp, horario, tel1, tel2, email }
 */

defined( 'ABSPATH' ) || exit;

$cnx_whatsapp = (string) ( $args['whatsapp'] ?? '' );
$cnx_horario  = (string) ( $args['horario'] ?? '' );
$cnx_tel1     = (string) ( $args['tel1'] ?? '' );
$cnx_tel2     = (string) ( $args['tel2'] ?? '' );
$cnx_email    = (string) ( $args['email'] ?? '' );
$cnx_endereco = (string) get_option( 'cnx_endereco', '' );
$cnx_mapa     = (string) get_option( 'cnx_mapa_embed', '' );
$cnx_tipos    = function_exists( 'cnx_tipos_de_servico' ) ? cnx_tipos_de_servico() : array();
$cnx_status   = isset( $_GET['cnx_lead'] ) ? sanitize_key( wp_unslash( $_GET['cnx_lead'] ) ) : '';

$cnx_tel_link = static fn( string $tel ): string => (string) preg_replace( '/\D/', '', $tel );
?>

<section class="cnx-secao cnx-contato" id="cnx-contato">
	<div class="cnx-secao__inner cnx-contato__grade">

		<div class="cnx-contato__coluna">
			<h1 class="cnx-contato__titulo"><?php esc_html_e( 'Fale com a Conexão Gráfica', 'conexao' ); ?></h1>

			<p class="cnx-contato__texto">
				<?php esc_html_e( 'Estamos prontos para entender seu projeto e entregar resultados que conectam.', 'conexao' ); ?>
			</p>

			<ul class="cnx-canais">
				<?php if ( '' !== $cnx_whatsapp ) : ?>
					<li class="cnx-canal">
						<a href="<?php echo esc_url( $cnx_whatsapp ); ?>" target="_blank" rel="noopener">
							<img src="<?php echo esc_url( get_theme_file_uri( 'assets/img/wpp.png' ) ); ?>"
								alt="" width="21" height="23" aria-hidden="true">
							<span>
								<strong><?php esc_html_e( 'WhatsApp', 'conexao' ); ?></strong>
								<?php if ( '' !== $cnx_tel1 ) : ?>
									<span><?php echo esc_html( $cnx_tel1 ); ?></span>
								<?php endif; ?>
								<?php if ( '' !== $cnx_tel2 ) : ?>
									<span><?php echo esc_html( $cnx_tel2 ); ?></span>
								<?php endif; ?>
							</span>
						</a>
					</li>
				<?php endif; ?>

				<?php if ( '' !== $cnx_email ) : ?>
					<li class="cnx-canal">
						<a href="mailto:<?php echo esc_attr( $cnx_email ); ?>">
							<span class="cnx-canal__icone" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
									stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false">
									<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>
								</svg>
							</span>
							<span>
								<strong><?php esc_html_e( 'E-mail', 'conexao' ); ?></strong>
								<span><?php echo esc_html( $cnx_email ); ?></span>
							</span>
						</a>
					</li>
				<?php endif; ?>

				<?php if ( '' !== $cnx_horario ) : ?>
					<li class="cnx-canal">
						<span class="cnx-canal__icone" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
								stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false">
								<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
							</svg>
						</span>
						<span>
							<strong><?php esc_html_e( 'Horários', 'conexao' ); ?></strong>
							<span><?php echo esc_html( $cnx_horario ); ?></span>
						</span>
					</li>
				<?php endif; ?>

				<?php if ( '' !== $cnx_endereco ) : ?>
					<li class="cnx-canal">
						<span class="cnx-canal__icone" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
								stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false">
								<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>
							</svg>
						</span>
						<span>
							<strong><?php esc_html_e( 'Endereço', 'conexao' ); ?></strong>
							<span><?php echo esc_html( $cnx_endereco ); ?></span>
						</span>
					</li>
				<?php endif; ?>
			</ul>

			<h2 class="cnx-contato__subtitulo"><?php esc_html_e( 'Envie sua mensagem', 'conexao' ); ?></h2>

			<?php if ( 'ok' === $cnx_status ) : ?>
				<p class="cnx-aviso cnx-aviso--sucesso cnx-aviso--claro" role="status">
					<?php esc_html_e( 'Mensagem recebida! Entraremos em contato em breve.', 'conexao' ); ?>
				</p>
			<?php else : ?>

				<?php if ( 'dados' === $cnx_status ) : ?>
					<p class="cnx-aviso cnx-aviso--erro cnx-aviso--claro" role="alert">
						<?php esc_html_e( 'Confira o nome e o e-mail e tente de novo.', 'conexao' ); ?>
					</p>
				<?php elseif ( 'erro' === $cnx_status ) : ?>
					<p class="cnx-aviso cnx-aviso--erro cnx-aviso--claro" role="alert">
						<?php esc_html_e( 'Não foi possível enviar agora. Tente novamente em instantes.', 'conexao' ); ?>
					</p>
				<?php endif; ?>

				<p class="cnx-contato__texto">
					<?php esc_html_e( 'Preencha os campos abaixo e entraremos em contato.', 'conexao' ); ?>
				</p>

				<form class="cnx-form-contato" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="cnx_mensagem">
					<?php wp_nonce_field( 'cnx_mensagem', 'cnx_mensagem_nonce' ); ?>

					<label class="screen-reader-text" for="cnx_c_nome"><?php esc_html_e( 'Nome', 'conexao' ); ?></label>
					<input type="text" id="cnx_c_nome" name="cnx_nome" class="cnx-campo"
						placeholder="<?php esc_attr_e( 'Nome', 'conexao' ); ?>" required autocomplete="name">

					<label class="screen-reader-text" for="cnx_c_email"><?php esc_html_e( 'E-mail', 'conexao' ); ?></label>
					<input type="email" id="cnx_c_email" name="cnx_email" class="cnx-campo"
						placeholder="<?php esc_attr_e( 'E-mail', 'conexao' ); ?>" required autocomplete="email">

					<label class="screen-reader-text" for="cnx_c_tel"><?php esc_html_e( 'Telefone', 'conexao' ); ?></label>
					<input type="tel" id="cnx_c_tel" name="cnx_telefone" class="cnx-campo"
						placeholder="<?php esc_attr_e( 'Telefone', 'conexao' ); ?>" autocomplete="tel">

					<label class="screen-reader-text" for="cnx_c_tipo"><?php esc_html_e( 'Tipo de serviço', 'conexao' ); ?></label>
					<select id="cnx_c_tipo" name="cnx_tipo" class="cnx-campo cnx-campo--select">
						<option value=""><?php esc_html_e( 'Tipo de serviço', 'conexao' ); ?></option>
						<?php foreach ( $cnx_tipos as $cnx_tipo ) : ?>
							<option value="<?php echo esc_attr( $cnx_tipo ); ?>"><?php echo esc_html( $cnx_tipo ); ?></option>
						<?php endforeach; ?>
					</select>

					<label class="screen-reader-text" for="cnx_c_msg"><?php esc_html_e( 'Mensagem', 'conexao' ); ?></label>
					<textarea id="cnx_c_msg" name="cnx_mensagem" class="cnx-campo" rows="5"
						placeholder="<?php esc_attr_e( 'Mensagem', 'conexao' ); ?>"></textarea>

					<div class="cnx-form__armadilha" aria-hidden="true">
						<label for="cnx_c_site"><?php esc_html_e( 'Deixe em branco', 'conexao' ); ?></label>
						<input type="text" id="cnx_c_site" name="cnx_site" tabindex="-1" autocomplete="off">
					</div>

					<button type="submit" class="cnx-form-contato__enviar">
						<?php esc_html_e( 'Enviar', 'conexao' ); ?>
					</button>
				</form>
			<?php endif; ?>
		</div>

		<div class="cnx-contato__coluna">
			<?php if ( '' !== $cnx_mapa ) : ?>
				<?php // loading=lazy: o mapa é de terceiro, só carrega se chegar perto. ?>
				<iframe class="cnx-mapa" src="<?php echo esc_url( $cnx_mapa ); ?>"
					title="<?php esc_attr_e( 'Localização da Conexão Gráfica', 'conexao' ); ?>"
					loading="lazy" referrerpolicy="no-referrer-when-downgrade"
					allowfullscreen></iframe>
			<?php elseif ( current_user_can( 'manage_options' ) ) : ?>
				<p class="cnx-mapa cnx-mapa--vazio">
					<?php esc_html_e( 'Cole a URL de incorporação do mapa em Configurações → Conexão para exibi-lo aqui.', 'conexao' ); ?>
				</p>
			<?php endif; ?>
		</div>

	</div>
</section>
