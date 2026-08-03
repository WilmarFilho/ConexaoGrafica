<?php
/**
 * Avatar local dos autores.
 *
 * O tema mostra o avatar nos cards e no post; depender do Gravatar deixa a
 * imagem quebrada para quem não tem conta lá. Aqui o autor ganha um campo
 * "Foto de perfil" no próprio perfil, e quem não tiver foto cai num padrão
 * local com o símbolo da marca — nunca numa imagem externa.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Descobre o usuário por trás do que get_avatar() recebeu (ID, e-mail ou objeto).
 */
function cnx_usuario_do_avatar( $id_or_email ): ?WP_User {
	if ( is_numeric( $id_or_email ) ) {
		$usuario = get_user_by( 'id', (int) $id_or_email );
	} elseif ( $id_or_email instanceof WP_User ) {
		$usuario = $id_or_email;
	} elseif ( $id_or_email instanceof WP_Post ) {
		$usuario = get_user_by( 'id', (int) $id_or_email->post_author );
	} elseif ( $id_or_email instanceof WP_Comment ) {
		$usuario = $id_or_email->user_id ? get_user_by( 'id', (int) $id_or_email->user_id ) : get_user_by( 'email', $id_or_email->comment_author_email );
	} elseif ( is_string( $id_or_email ) ) {
		$usuario = get_user_by( 'email', $id_or_email );
	} else {
		$usuario = false;
	}

	return $usuario instanceof WP_User ? $usuario : null;
}

add_filter( 'pre_get_avatar_data', 'cnx_avatar_local', 10, 2 );

function cnx_avatar_local( array $args, $id_or_email ): array {
	$usuario = cnx_usuario_do_avatar( $id_or_email );

	if ( $usuario ) {
		$anexo = (int) get_user_meta( $usuario->ID, 'cnx_avatar', true );
		$url   = $anexo ? wp_get_attachment_image_url( $anexo, 'thumbnail' ) : false;

		if ( $url ) {
			$args['url']          = $url;
			$args['found_avatar'] = true;

			return $args;
		}
	}

	// Sem foto cadastrada: símbolo da marca, servido pelo tema.
	$args['url']          = get_theme_file_uri( 'assets/img/favicon.png' );
	$args['found_avatar'] = true;
	$args['class']        = array_merge( (array) ( $args['class'] ?? array() ), array( 'cnx-avatar--marca' ) );

	return $args;
}

/* ---------------------------------------------------------------
 * Campo "Foto de perfil" no perfil do usuário
 * --------------------------------------------------------------- */

add_action( 'show_user_profile', 'cnx_avatar_campo' );
add_action( 'edit_user_profile', 'cnx_avatar_campo' );

function cnx_avatar_campo( WP_User $usuario ): void {
	$anexo = (int) get_user_meta( $usuario->ID, 'cnx_avatar', true );
	$url   = $anexo ? wp_get_attachment_image_url( $anexo, 'thumbnail' ) : '';
	?>
	<h2><?php esc_html_e( 'Foto de perfil no site', 'conexao' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label><?php esc_html_e( 'Foto de perfil', 'conexao' ); ?></label></th>
			<td>
				<div class="cnx-galeria" data-cnx-galeria data-cnx-unica>
					<input type="hidden" name="cnx_avatar" value="<?php echo esc_attr( $anexo ? (string) $anexo : '' ); ?>">
					<ul class="cnx-galeria__list">
						<?php if ( $url ) : ?>
							<li class="cnx-galeria__item" data-id="<?php echo esc_attr( (string) $anexo ); ?>">
								<img src="<?php echo esc_url( $url ); ?>" alt="">
								<button type="button" class="cnx-galeria__remove">✕</button>
							</li>
						<?php endif; ?>
					</ul>
					<button type="button" class="button cnx-galeria__add"><?php esc_html_e( 'Escolher imagem', 'conexao' ); ?></button>
				</div>
				<p class="description"><?php esc_html_e( 'Aparece nos cards e nas páginas dos posts do blog. Sem foto, o site usa o símbolo da Conexão.', 'conexao' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

add_action( 'personal_options_update', 'cnx_avatar_salvar' );
add_action( 'edit_user_profile_update', 'cnx_avatar_salvar' );

function cnx_avatar_salvar( int $usuario_id ): void {
	if ( ! current_user_can( 'edit_user', $usuario_id ) || ! isset( $_POST['cnx_avatar'] ) ) {
		return;
	}

	$anexo = absint( wp_unslash( $_POST['cnx_avatar'] ) );

	if ( $anexo ) {
		update_user_meta( $usuario_id, 'cnx_avatar', $anexo );
	} else {
		delete_user_meta( $usuario_id, 'cnx_avatar' );
	}
}
