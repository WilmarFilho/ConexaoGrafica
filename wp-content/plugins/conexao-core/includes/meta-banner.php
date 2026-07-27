<?php
/**
 * Campos do banner de chamada.
 *
 * A foto de fundo é a imagem destacada. O slug do banner é o que o shortcode usa:
 * [cnx_banner slug="parceria-estrategica"]
 */

defined( 'ABSPATH' ) || exit;

add_action( 'add_meta_boxes', 'cnx_register_banner_meta_boxes' );

function cnx_register_banner_meta_boxes(): void {
	add_meta_box(
		'cnx_banner_conteudo',
		__( 'Conteúdo do banner', 'conexao' ),
		'cnx_render_box_banner',
		CNX_CPT_BANNER,
		'normal',
		'high'
	);
}

function cnx_render_box_banner( WP_Post $post ): void {
	wp_nonce_field( 'cnx_save_banner', 'cnx_banner_nonce' );

	$titulo  = cnx_meta( $post->ID, 'banner_titulo' );
	$texto   = cnx_meta( $post->ID, 'banner_texto' );
	$btn_txt = cnx_meta( $post->ID, 'banner_btn_txt' );
	$btn_url = cnx_meta( $post->ID, 'banner_btn_url' );
	?>
	<div class="cnx-fields">
		<p class="cnx-field">
			<label for="cnx_banner_titulo"><strong><?php esc_html_e( 'Título', 'conexao' ); ?></strong></label>
			<textarea id="cnx_banner_titulo" name="cnx_banner_titulo" rows="2" class="large-text"><?php echo esc_textarea( $titulo ); ?></textarea>
			<span class="description">
				<?php esc_html_e( 'Use <strong>...</strong> para destacar parte da frase e <br> para quebrar linha.', 'conexao' ); ?>
			</span>
		</p>

		<p class="cnx-field">
			<label for="cnx_banner_texto"><strong><?php esc_html_e( 'Descrição', 'conexao' ); ?></strong></label>
			<textarea id="cnx_banner_texto" name="cnx_banner_texto" rows="3" class="large-text"><?php echo esc_textarea( $texto ); ?></textarea>
		</p>

		<div class="cnx-row__grid">
			<p class="cnx-field">
				<label for="cnx_banner_btn_txt"><strong><?php esc_html_e( 'Botão — texto', 'conexao' ); ?></strong></label>
				<input type="text" id="cnx_banner_btn_txt" name="cnx_banner_btn_txt" value="<?php echo esc_attr( $btn_txt ); ?>" placeholder="<?php esc_attr_e( 'Solicitar Orçamento', 'conexao' ); ?>">
			</p>

			<p class="cnx-field">
				<label for="cnx_banner_btn_url"><strong><?php esc_html_e( 'Botão — link', 'conexao' ); ?></strong></label>
				<input type="text" id="cnx_banner_btn_url" name="cnx_banner_btn_url" value="<?php echo esc_attr( $btn_url ); ?>" placeholder="<?php echo esc_attr( home_url( '/orcamento/' ) ); ?>">
			</p>
		</div>

		<p class="description">
			<?php
			printf(
				/* translators: %s: shortcode de exemplo */
				esc_html__( 'Para exibir este banner numa página, use: %s', 'conexao' ),
				'<code>[cnx_banner slug="' . esc_html( $post->post_name ?: 'slug-do-banner' ) . '"]</code>'
			);
			?>
		</p>
	</div>
	<?php
}

add_action( 'save_post_' . CNX_CPT_BANNER, 'cnx_save_banner_meta', 10, 2 );

function cnx_save_banner_meta( int $post_id, WP_Post $post ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! isset( $_POST['cnx_banner_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cnx_banner_nonce'] ) ), 'cnx_save_banner' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$titulo = isset( $_POST['cnx_banner_titulo'] )
		? wp_kses( wp_unslash( $_POST['cnx_banner_titulo'] ), cnx_slide_html_permitido() )
		: '';
	cnx_update_meta( $post_id, 'banner_titulo', trim( $titulo ) );

	$texto = isset( $_POST['cnx_banner_texto'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cnx_banner_texto'] ) ) : '';
	cnx_update_meta( $post_id, 'banner_texto', $texto );

	$btn_txt = isset( $_POST['cnx_banner_btn_txt'] ) ? sanitize_text_field( wp_unslash( $_POST['cnx_banner_btn_txt'] ) ) : '';
	cnx_update_meta( $post_id, 'banner_btn_txt', $btn_txt );

	$btn_url = isset( $_POST['cnx_banner_btn_url'] )
		? esc_url_raw( trim( (string) wp_unslash( $_POST['cnx_banner_btn_url'] ) ) )
		: '';
	cnx_update_meta( $post_id, 'banner_btn_url', $btn_url );
}

/**
 * Slug e shortcode visíveis na listagem — é o que se copia para a página.
 */
add_filter( 'manage_' . CNX_CPT_BANNER . '_posts_columns', 'cnx_banner_columns' );

function cnx_banner_columns( array $columns ): array {
	return array(
		'cb'            => $columns['cb'] ?? '',
		'cnx_thumb'     => __( 'Fundo', 'conexao' ),
		'title'         => __( 'Banner', 'conexao' ),
		'cnx_shortcode' => __( 'Shortcode', 'conexao' ),
		'date'          => $columns['date'] ?? __( 'Data', 'conexao' ),
	);
}

add_action( 'manage_' . CNX_CPT_BANNER . '_posts_custom_column', 'cnx_banner_column_content', 10, 2 );

function cnx_banner_column_content( string $column, int $post_id ): void {
	switch ( $column ) {
		case 'cnx_thumb':
			echo has_post_thumbnail( $post_id )
				? get_the_post_thumbnail( $post_id, array( 80, 50 ), array( 'style' => 'border-radius:3px;object-fit:cover;' ) )
				: '<span style="color:#d63638;">' . esc_html__( 'sem foto', 'conexao' ) . '</span>';
			break;

		case 'cnx_shortcode':
			printf(
				'<code>[cnx_banner slug="%s"]</code>',
				esc_html( (string) get_post_field( 'post_name', $post_id ) )
			);
			break;
	}
}
