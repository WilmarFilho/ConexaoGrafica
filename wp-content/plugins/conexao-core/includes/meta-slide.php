<?php
/**
 * Campos do slide do carrossel.
 *
 * O título do post é só um rótulo interno (aparece na listagem do admin).
 * O texto que vai para a tela é o campo "Título exibido", que aceita <strong>
 * para destacar parte da frase — nos slides o negrito muda de lugar:
 *
 *   <strong>Impressão profissional</strong> para empresas, clínicas e editoras.
 *   Papelaria profissional <strong>para Advogados</strong>
 */

defined( 'ABSPATH' ) || exit;

/**
 * HTML permitido no título do slide.
 */
function cnx_slide_html_permitido(): array {
	return array(
		'strong' => array(),
		'b'      => array(),
		'em'     => array(),
		'br'     => array(),
	);
}

add_action( 'add_meta_boxes', 'cnx_register_slide_meta_boxes' );

function cnx_register_slide_meta_boxes(): void {
	add_meta_box(
		'cnx_slide_conteudo',
		__( 'Conteúdo do slide', 'conexao' ),
		'cnx_render_box_slide',
		CNX_CPT_SLIDE,
		'normal',
		'high'
	);

	add_meta_box(
		'cnx_slide_fundo',
		__( 'Imagem de fundo', 'conexao' ),
		'cnx_render_box_slide_fundo',
		CNX_CPT_SLIDE,
		'side',
		'low'
	);
}

function cnx_render_box_slide( WP_Post $post ): void {
	wp_nonce_field( 'cnx_save_slide', 'cnx_slide_nonce' );

	$titulo   = cnx_meta( $post->ID, 'slide_titulo' );
	$texto    = cnx_meta( $post->ID, 'slide_texto' );
	$b1_txt   = cnx_meta( $post->ID, 'slide_btn1_txt' );
	$b1_url   = cnx_meta( $post->ID, 'slide_btn1_url' );
	$b2_txt   = cnx_meta( $post->ID, 'slide_btn2_txt' );
	$b2_url   = cnx_meta( $post->ID, 'slide_btn2_url' );
	?>
	<div class="cnx-fields">
		<p class="cnx-field">
			<label for="cnx_slide_titulo"><strong><?php esc_html_e( 'Título exibido', 'conexao' ); ?></strong></label>
			<textarea id="cnx_slide_titulo" name="cnx_slide_titulo" rows="2" class="large-text"><?php echo esc_textarea( $titulo ); ?></textarea>
			<span class="description">
				<?php esc_html_e( 'Use <strong>...</strong> para destacar parte da frase.', 'conexao' ); ?>
			</span>
		</p>

		<p class="cnx-field">
			<label for="cnx_slide_texto"><strong><?php esc_html_e( 'Descrição', 'conexao' ); ?></strong></label>
			<textarea id="cnx_slide_texto" name="cnx_slide_texto" rows="3" class="large-text"><?php echo esc_textarea( $texto ); ?></textarea>
		</p>

		<div class="cnx-row__grid">
			<p class="cnx-field">
				<label for="cnx_slide_btn1_txt"><strong><?php esc_html_e( 'Botão principal — texto', 'conexao' ); ?></strong></label>
				<input type="text" id="cnx_slide_btn1_txt" name="cnx_slide_btn1_txt" value="<?php echo esc_attr( $b1_txt ); ?>" placeholder="<?php esc_attr_e( 'Solicitar Orçamento', 'conexao' ); ?>">
			</p>

			<p class="cnx-field">
				<label for="cnx_slide_btn1_url"><strong><?php esc_html_e( 'Botão principal — link', 'conexao' ); ?></strong></label>
				<input type="text" id="cnx_slide_btn1_url" name="cnx_slide_btn1_url" value="<?php echo esc_attr( $b1_url ); ?>" placeholder="<?php echo esc_attr( home_url( '/orcamento/' ) ); ?>">
			</p>

			<p class="cnx-field">
				<label for="cnx_slide_btn2_txt"><strong><?php esc_html_e( 'Botão secundário — texto', 'conexao' ); ?></strong></label>
				<input type="text" id="cnx_slide_btn2_txt" name="cnx_slide_btn2_txt" value="<?php echo esc_attr( $b2_txt ); ?>" placeholder="<?php esc_attr_e( 'Ver Produtos', 'conexao' ); ?>">
			</p>

			<p class="cnx-field">
				<label for="cnx_slide_btn2_url"><strong><?php esc_html_e( 'Botão secundário — link', 'conexao' ); ?></strong></label>
				<input type="text" id="cnx_slide_btn2_url" name="cnx_slide_btn2_url" value="<?php echo esc_attr( $b2_url ); ?>" placeholder="<?php echo esc_attr( (string) get_post_type_archive_link( CNX_CPT_PRODUTO ) ); ?>">
			</p>
		</div>

		<p class="description">
			<?php esc_html_e( 'A imagem da coluna direita é a "Imagem do produto" (imagem destacada). A ordem dos slides é o campo "Ordem" em Atributos.', 'conexao' ); ?>
		</p>
	</div>
	<?php
}

function cnx_render_box_slide_fundo( WP_Post $post ): void {
	$bg_id = (int) cnx_meta( $post->ID, 'slide_bg', 0 );
	?>
	<div class="cnx-galeria" data-cnx-galeria data-cnx-unica>
		<ul class="cnx-galeria__list">
			<?php if ( $bg_id ) : ?>
				<li class="cnx-galeria__item" data-id="<?php echo esc_attr( (string) $bg_id ); ?>">
					<?php echo wp_get_attachment_image( $bg_id, 'thumbnail' ); ?>
					<button type="button" class="cnx-galeria__remove" aria-label="<?php esc_attr_e( 'Remover imagem', 'conexao' ); ?>">✕</button>
				</li>
			<?php endif; ?>
		</ul>

		<input type="hidden" name="cnx_slide_bg" value="<?php echo esc_attr( $bg_id ? (string) $bg_id : '' ); ?>">

		<button type="button" class="button button-secondary cnx-galeria__add">
			<?php esc_html_e( 'Selecionar imagem', 'conexao' ); ?>
		</button>

		<p class="description" style="margin-top:8px;">
			<?php esc_html_e( 'Opcional. Sem imagem, o slide usa o fundo padrão do tema.', 'conexao' ); ?>
		</p>
	</div>
	<?php
}

add_action( 'save_post_' . CNX_CPT_SLIDE, 'cnx_save_slide_meta', 10, 2 );

function cnx_save_slide_meta( int $post_id, WP_Post $post ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! isset( $_POST['cnx_slide_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cnx_slide_nonce'] ) ), 'cnx_save_slide' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$titulo = isset( $_POST['cnx_slide_titulo'] )
		? wp_kses( wp_unslash( $_POST['cnx_slide_titulo'] ), cnx_slide_html_permitido() )
		: '';
	cnx_update_meta( $post_id, 'slide_titulo', trim( $titulo ) );

	$texto = isset( $_POST['cnx_slide_texto'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cnx_slide_texto'] ) ) : '';
	cnx_update_meta( $post_id, 'slide_texto', $texto );

	foreach ( array( 'btn1', 'btn2' ) as $botao ) {
		$txt = isset( $_POST[ "cnx_slide_{$botao}_txt" ] )
			? sanitize_text_field( wp_unslash( $_POST[ "cnx_slide_{$botao}_txt" ] ) )
			: '';

		$url = isset( $_POST[ "cnx_slide_{$botao}_url" ] )
			? esc_url_raw( trim( (string) wp_unslash( $_POST[ "cnx_slide_{$botao}_url" ] ) ) )
			: '';

		cnx_update_meta( $post_id, "slide_{$botao}_txt", $txt );
		cnx_update_meta( $post_id, "slide_{$botao}_url", $url );
	}

	$bg = isset( $_POST['cnx_slide_bg'] ) ? absint( $_POST['cnx_slide_bg'] ) : 0;
	cnx_update_meta( $post_id, 'slide_bg', $bg ? (string) $bg : '' );
}

/**
 * Colunas da listagem de slides: sem isso a tela é só uma lista de títulos.
 */
add_filter( 'manage_' . CNX_CPT_SLIDE . '_posts_columns', 'cnx_slide_columns' );

function cnx_slide_columns( array $columns ): array {
	return array(
		'cb'         => $columns['cb'] ?? '',
		'cnx_thumb'  => __( 'Imagem', 'conexao' ),
		'title'      => __( 'Slide', 'conexao' ),
		'cnx_titulo' => __( 'Título exibido', 'conexao' ),
		'cnx_ordem'  => __( 'Ordem', 'conexao' ),
		'date'       => $columns['date'] ?? __( 'Data', 'conexao' ),
	);
}

add_action( 'manage_' . CNX_CPT_SLIDE . '_posts_custom_column', 'cnx_slide_column_content', 10, 2 );

function cnx_slide_column_content( string $column, int $post_id ): void {
	switch ( $column ) {
		case 'cnx_thumb':
			echo has_post_thumbnail( $post_id )
				? get_the_post_thumbnail( $post_id, array( 70, 70 ), array( 'style' => 'border-radius:4px;object-fit:contain;background:#f1f1f1;' ) )
				: '—';
			break;

		case 'cnx_titulo':
			echo wp_kses( (string) cnx_meta( $post_id, 'slide_titulo' ), cnx_slide_html_permitido() );
			break;

		case 'cnx_ordem':
			echo (int) get_post_field( 'menu_order', $post_id );
			break;
	}
}

add_filter( 'manage_edit-' . CNX_CPT_SLIDE . '_sortable_columns', 'cnx_slide_sortable' );

function cnx_slide_sortable( array $columns ): array {
	$columns['cnx_ordem'] = 'menu_order';

	return $columns;
}

/**
 * A listagem já sai na ordem em que os slides aparecem no site.
 */
add_action( 'pre_get_posts', 'cnx_slide_admin_order' );

function cnx_slide_admin_order( WP_Query $query ): void {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( CNX_CPT_SLIDE !== $query->get( 'post_type' ) || $query->get( 'orderby' ) ) {
		return;
	}

	$query->set( 'orderby', 'menu_order' );
	$query->set( 'order', 'ASC' );
}
