<?php
/**
 * Configurações globais do site (Configurações → Conexão).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'cnx_settings_menu' );

function cnx_settings_menu(): void {
	add_options_page(
		__( 'Conexão Gráfica', 'conexao' ),
		__( 'Conexão', 'conexao' ),
		'manage_options',
		'cnx-settings',
		'cnx_render_settings_page'
	);
}

add_action( 'admin_init', 'cnx_register_settings' );

function cnx_register_settings(): void {
	register_setting(
		'cnx_settings',
		'cnx_whatsapp_numero',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'cnx_sanitize_telefone',
			'default'           => '',
		)
	);

	register_setting(
		'cnx_settings',
		'cnx_orcamento_url',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => '',
		)
	);

	// Contato e redes exibidos no rodapé.
	$campos_texto = array(
		'cnx_horario'         => 'sanitize_text_field',
		'cnx_telefone_1'      => 'sanitize_text_field',
		'cnx_telefone_2'      => 'sanitize_text_field',
		'cnx_email_comercial' => 'sanitize_email',
		'cnx_instagram'       => 'esc_url_raw',
		'cnx_facebook'        => 'esc_url_raw',
		'cnx_youtube'         => 'esc_url_raw',
		'cnx_sobre_curto'     => 'sanitize_textarea_field',
		'cnx_endereco'        => 'sanitize_textarea_field',
		'cnx_mapa_embed'      => 'esc_url_raw',
		'cnx_seo_titulo'      => 'sanitize_text_field',
		'cnx_seo_descricao'   => 'sanitize_textarea_field',
	);

	foreach ( $campos_texto as $chave => $sanitizer ) {
		register_setting(
			'cnx_settings',
			$chave,
			array(
				'type'              => 'string',
				'sanitize_callback' => $sanitizer,
				'default'           => '',
			)
		);
	}

	register_setting(
		'cnx_settings',
		'cnx_whatsapp_saudacao',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => 'Olá! Vim pelo site e quero um orçamento.',
		)
	);
}

function cnx_render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Conexão Gráfica', 'conexao' ); ?></h1>

		<form method="post" action="options.php">
			<?php settings_fields( 'cnx_settings' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="cnx_whatsapp_numero"><?php esc_html_e( 'WhatsApp (padrão)', 'conexao' ); ?></label>
					</th>
					<td>
						<input type="text" id="cnx_whatsapp_numero" name="cnx_whatsapp_numero" class="regular-text"
							value="<?php echo esc_attr( get_option( 'cnx_whatsapp_numero', '' ) ); ?>"
							placeholder="5521999999999">
						<p class="description">
							<?php esc_html_e( 'Com DDI e DDD, só números. Ex.: 5521999999999. Produtos podem sobrescrever este número.', 'conexao' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cnx_whatsapp_saudacao"><?php esc_html_e( 'Abertura da mensagem', 'conexao' ); ?></label>
					</th>
					<td>
						<textarea id="cnx_whatsapp_saudacao" name="cnx_whatsapp_saudacao" rows="3" class="large-text"><?php
							echo esc_textarea( get_option( 'cnx_whatsapp_saudacao', '' ) );
						?></textarea>
						<p class="description">
							<?php esc_html_e( 'Primeira linha da mensagem. As escolhas do cliente são anexadas logo abaixo.', 'conexao' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cnx_orcamento_url"><?php esc_html_e( 'Página de orçamento', 'conexao' ); ?></label>
					</th>
					<td>
						<input type="url" id="cnx_orcamento_url" name="cnx_orcamento_url" class="regular-text"
							value="<?php echo esc_attr( get_option( 'cnx_orcamento_url', '' ) ); ?>"
							placeholder="<?php echo esc_attr( home_url( '/orcamento/' ) ); ?>">
						<p class="description">
							<?php esc_html_e( 'Destino do botão "Solicitar Orçamento" da topbar. Se vazio, o botão abre o WhatsApp.', 'conexao' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'SEO da página inicial', 'conexao' ); ?></h2>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="cnx_seo_titulo"><?php esc_html_e( 'Título para buscadores', 'conexao' ); ?></label>
					</th>
					<td>
						<input type="text" id="cnx_seo_titulo" name="cnx_seo_titulo" class="large-text"
							value="<?php echo esc_attr( get_option( 'cnx_seo_titulo', '' ) ); ?>"
							placeholder="<?php esc_attr_e( 'Gráfica em Goiânia | Impressão profissional — Conexão Gráfica', 'conexao' ); ?>">
						<p class="description"><?php esc_html_e( 'Até ~60 caracteres. Vazio usa o nome e a descrição do site.', 'conexao' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="cnx_seo_descricao"><?php esc_html_e( 'Descrição para buscadores', 'conexao' ); ?></label>
					</th>
					<td>
						<textarea id="cnx_seo_descricao" name="cnx_seo_descricao" rows="3" class="large-text"><?php
							echo esc_textarea( get_option( 'cnx_seo_descricao', '' ) );
						?></textarea>
						<p class="description"><?php esc_html_e( 'Até ~160 caracteres. Aparece no resultado do Google e ao compartilhar o link.', 'conexao' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Rodapé — contato', 'conexao' ); ?></h2>

			<table class="form-table" role="presentation">
				<?php
				$cnx_campos_rodape = array(
					'cnx_horario'         => array( __( 'Horário de atendimento', 'conexao' ), '08:00 às 18:00 (seg. a sexta)', 'text' ),
					'cnx_telefone_1'      => array( __( 'Telefone 1', 'conexao' ), '62 99822-8022', 'text' ),
					'cnx_telefone_2'      => array( __( 'Telefone 2', 'conexao' ), '62 3229.6147', 'text' ),
					'cnx_email_comercial' => array( __( 'E-mail comercial', 'conexao' ), 'comercial@conexaografica.com.br', 'email' ),
					'cnx_instagram'       => array( __( 'Instagram', 'conexao' ), 'https://instagram.com/...', 'url' ),
					'cnx_facebook'        => array( __( 'Facebook', 'conexao' ), 'https://facebook.com/...', 'url' ),
					'cnx_youtube'         => array( __( 'YouTube', 'conexao' ), 'https://youtube.com/...', 'url' ),
				);

				foreach ( $cnx_campos_rodape as $cnx_chave => $cnx_campo ) :
					?>
					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( $cnx_chave ); ?>"><?php echo esc_html( $cnx_campo[0] ); ?></label>
						</th>
						<td>
							<input type="<?php echo esc_attr( $cnx_campo[2] ); ?>"
								id="<?php echo esc_attr( $cnx_chave ); ?>"
								name="<?php echo esc_attr( $cnx_chave ); ?>"
								class="regular-text"
								value="<?php echo esc_attr( get_option( $cnx_chave, '' ) ); ?>"
								placeholder="<?php echo esc_attr( $cnx_campo[1] ); ?>">
						</td>
					</tr>
				<?php endforeach; ?>

				<tr>
					<th scope="row">
						<label for="cnx_endereco"><?php esc_html_e( 'Endereço', 'conexao' ); ?></label>
					</th>
					<td>
						<textarea id="cnx_endereco" name="cnx_endereco" rows="2" class="large-text"><?php
							echo esc_textarea( get_option( 'cnx_endereco', '' ) );
						?></textarea>
						<p class="description"><?php esc_html_e( 'Exibido na página de Contato.', 'conexao' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="cnx_mapa_embed"><?php esc_html_e( 'Mapa (URL de incorporação)', 'conexao' ); ?></label>
					</th>
					<td>
						<input type="url" id="cnx_mapa_embed" name="cnx_mapa_embed" class="large-text"
							value="<?php echo esc_attr( get_option( 'cnx_mapa_embed', '' ) ); ?>"
							placeholder="https://www.google.com/maps/embed?pb=...">
						<p class="description">
							<?php esc_html_e( 'No Google Maps: Compartilhar → Incorporar um mapa → copie somente o endereço do src do iframe. Vazio esconde o mapa.', 'conexao' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="cnx_sobre_curto"><?php esc_html_e( 'Descrição curta', 'conexao' ); ?></label>
					</th>
					<td>
						<textarea id="cnx_sobre_curto" name="cnx_sobre_curto" rows="3" class="large-text"><?php
							echo esc_textarea( get_option( 'cnx_sobre_curto', '' ) );
						?></textarea>
						<p class="description">
							<?php esc_html_e( 'Parágrafo ao lado da logo no rodapé.', 'conexao' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
