<?php
/**
 * Campos personalizados do produto (metaboxes).
 *
 * Modelo de dados:
 *   _cnx_sku            string
 *   _cnx_resumo         string  (frase curta abaixo do título)
 *   _cnx_qtd_minima     int
 *   _cnx_prazo          string
 *   _cnx_destaque       '1' | ''
 *   _cnx_whatsapp       string  (sobrescreve o número global)
 *   _cnx_galeria        string  (IDs de anexos separados por vírgula)
 *   _cnx_config_grupos  array   [ ['titulo','ajuda','obrigatorio','opcoes'[]], ... ]
 *   _cnx_blocos         array   [ ['titulo','conteudo'], ... ]
 */

defined( 'ABSPATH' ) || exit;

add_action( 'add_meta_boxes', 'cnx_register_meta_boxes' );

function cnx_register_meta_boxes(): void {
	add_meta_box(
		'cnx_produto_dados',
		__( 'Dados do produto', 'conexao' ),
		'cnx_render_box_dados',
		CNX_CPT_PRODUTO,
		'normal',
		'high'
	);

	add_meta_box(
		'cnx_produto_config',
		__( 'Configuração do orçamento', 'conexao' ),
		'cnx_render_box_config',
		CNX_CPT_PRODUTO,
		'normal',
		'high'
	);

	add_meta_box(
		'cnx_produto_blocos',
		__( 'Blocos de conteúdo (accordion)', 'conexao' ),
		'cnx_render_box_blocos',
		CNX_CPT_PRODUTO,
		'normal',
		'default'
	);

	add_meta_box(
		'cnx_produto_galeria',
		__( 'Galeria de imagens', 'conexao' ),
		'cnx_render_box_galeria',
		CNX_CPT_PRODUTO,
		'side',
		'default'
	);
}

/* -------------------------------------------------------------------------
 * Renderização
 * ------------------------------------------------------------------------- */

function cnx_nonce_field(): void {
	wp_nonce_field( 'cnx_save_produto', 'cnx_produto_nonce' );
}

function cnx_render_box_dados( WP_Post $post ): void {
	cnx_nonce_field();

	$sku      = cnx_meta( $post->ID, 'sku' );
	$resumo   = cnx_meta( $post->ID, 'resumo' );
	$qtd_min  = cnx_meta( $post->ID, 'qtd_minima' );
	$prazo    = cnx_meta( $post->ID, 'prazo' );
	$destaque = cnx_meta( $post->ID, 'destaque' );
	$whats    = cnx_meta( $post->ID, 'whatsapp' );
	$fundo    = cnx_meta( $post->ID, 'fundo' );
	?>
	<div class="cnx-fields">
		<p class="cnx-field">
			<label for="cnx_sku"><strong><?php esc_html_e( 'SKU / código interno', 'conexao' ); ?></strong></label>
			<input type="text" id="cnx_sku" name="cnx_sku" class="regular-text" value="<?php echo esc_attr( $sku ); ?>">
		</p>

		<p class="cnx-field">
			<label for="cnx_resumo"><strong><?php esc_html_e( 'Resumo', 'conexao' ); ?></strong></label>
			<textarea id="cnx_resumo" name="cnx_resumo" rows="3" class="large-text"><?php echo esc_textarea( $resumo ); ?></textarea>
			<span class="description"><?php esc_html_e( 'Frase curta exibida abaixo do título na página do produto e nos cards.', 'conexao' ); ?></span>
		</p>

		<p class="cnx-field cnx-field--inline">
			<label for="cnx_qtd_minima"><strong><?php esc_html_e( 'Quantidade mínima', 'conexao' ); ?></strong></label>
			<input type="number" id="cnx_qtd_minima" name="cnx_qtd_minima" min="0" step="1" value="<?php echo esc_attr( $qtd_min ); ?>">
		</p>

		<p class="cnx-field">
			<label for="cnx_prazo"><strong><?php esc_html_e( 'Prazo de produção', 'conexao' ); ?></strong></label>
			<input type="text" id="cnx_prazo" name="cnx_prazo" class="regular-text" value="<?php echo esc_attr( $prazo ); ?>" placeholder="<?php esc_attr_e( 'Ex.: 3 a 5 dias úteis', 'conexao' ); ?>">
		</p>

		<p class="cnx-field">
			<label for="cnx_whatsapp"><strong><?php esc_html_e( 'WhatsApp específico deste produto', 'conexao' ); ?></strong></label>
			<input type="text" id="cnx_whatsapp" name="cnx_whatsapp" class="regular-text" value="<?php echo esc_attr( $whats ); ?>" placeholder="5521999999999">
			<span class="description"><?php esc_html_e( 'Opcional. Se vazio, usa o número global em Configurações → Conexão.', 'conexao' ); ?></span>
		</p>

		<p class="cnx-field cnx-field--inline">
			<label for="cnx_fundo"><strong><?php esc_html_e( 'Cor de fundo do card', 'conexao' ); ?></strong></label>
			<input type="color" id="cnx_fundo" name="cnx_fundo" value="<?php echo esc_attr( $fundo ?: '#e2e2e2' ); ?>">
			<span class="description"><?php esc_html_e( 'Aparece atrás da imagem nas sobras do enquadramento, na vitrine e nas listagens.', 'conexao' ); ?></span>
		</p>

		<p class="cnx-field">
			<label>
				<input type="checkbox" name="cnx_destaque" value="1" <?php checked( $destaque, '1' ); ?>>
				<strong><?php esc_html_e( 'Exibir em "Mais vendidos" / destaques', 'conexao' ); ?></strong>
			</label>
		</p>
	</div>
	<?php
}

function cnx_render_box_config( WP_Post $post ): void {
	$grupos = cnx_meta( $post->ID, 'config_grupos', array() );
	$grupos = is_array( $grupos ) ? $grupos : array();
	?>
	<p class="description">
		<?php esc_html_e( 'Cada grupo vira um bloco de botões na página do produto (Quantidade, Arte do material, Prazo desejado...). As escolhas do cliente montam a mensagem enviada ao WhatsApp.', 'conexao' ); ?>
	</p>

	<div class="cnx-repeater" data-repeater="config" data-next-index="<?php echo esc_attr( (string) count( $grupos ) ); ?>">
		<div class="cnx-repeater__rows">
			<?php foreach ( $grupos as $i => $grupo ) : ?>
				<?php cnx_render_config_row( (int) $i, (array) $grupo ); ?>
			<?php endforeach; ?>
		</div>

		<template class="cnx-repeater__template">
			<?php cnx_render_config_row( -1, array() ); ?>
		</template>

		<button type="button" class="button button-secondary cnx-repeater__add">
			<?php esc_html_e( '+ Adicionar grupo', 'conexao' ); ?>
		</button>
	</div>
	<?php
}

function cnx_render_config_row( int $index, array $grupo ): void {
	// -1 é o índice do <template>; o JS troca por um número real ao clonar.
	$i           = ( -1 === $index ) ? '__INDEX__' : (string) $index;
	$titulo      = (string) ( $grupo['titulo'] ?? '' );
	$ajuda       = (string) ( $grupo['ajuda'] ?? '' );
	$obrigatorio = ! empty( $grupo['obrigatorio'] );
	$opcoes      = is_array( $grupo['opcoes'] ?? null ) ? implode( "\n", $grupo['opcoes'] ) : '';
	?>
	<div class="cnx-row">
		<div class="cnx-row__handle" title="<?php esc_attr_e( 'Arraste para reordenar', 'conexao' ); ?>">⠿</div>

		<div class="cnx-row__body">
			<div class="cnx-row__grid">
				<label class="cnx-field">
					<strong><?php esc_html_e( 'Título do grupo', 'conexao' ); ?></strong>
					<input type="text" name="cnx_config[<?php echo esc_attr( $i ); ?>][titulo]" value="<?php echo esc_attr( $titulo ); ?>" placeholder="<?php esc_attr_e( 'Quantidade', 'conexao' ); ?>">
				</label>

				<label class="cnx-field">
					<strong><?php esc_html_e( 'Texto de apoio', 'conexao' ); ?></strong>
					<input type="text" name="cnx_config[<?php echo esc_attr( $i ); ?>][ajuda]" value="<?php echo esc_attr( $ajuda ); ?>" placeholder="<?php esc_attr_e( 'Mínimo de 500 unidades', 'conexao' ); ?>">
				</label>
			</div>

			<label class="cnx-field">
				<strong><?php esc_html_e( 'Opções', 'conexao' ); ?></strong>
				<textarea name="cnx_config[<?php echo esc_attr( $i ); ?>][opcoes]" rows="4" placeholder="500 unidades&#10;1.000 unidades&#10;1.500 unidades&#10;+ 2.000 unidades"><?php echo esc_textarea( $opcoes ); ?></textarea>
				<span class="description"><?php esc_html_e( 'Uma opção por linha.', 'conexao' ); ?></span>
			</label>

			<label class="cnx-checkbox">
				<input type="checkbox" name="cnx_config[<?php echo esc_attr( $i ); ?>][obrigatorio]" value="1" <?php checked( $obrigatorio ); ?>>
				<?php esc_html_e( 'Obrigatório para liberar o botão de orçamento', 'conexao' ); ?>
			</label>
		</div>

		<button type="button" class="button-link cnx-row__remove" aria-label="<?php esc_attr_e( 'Remover grupo', 'conexao' ); ?>">✕</button>
	</div>
	<?php
}

function cnx_render_box_blocos( WP_Post $post ): void {
	$blocos = cnx_meta( $post->ID, 'blocos', array() );
	$blocos = is_array( $blocos ) ? $blocos : array();
	?>
	<p class="description">
		<?php esc_html_e( 'Seções recolhíveis abaixo da galeria: Materiais e acabamentos, Tamanhos e formatos, Prazos e entrega...', 'conexao' ); ?>
	</p>

	<div class="cnx-repeater" data-repeater="blocos" data-next-index="<?php echo esc_attr( (string) count( $blocos ) ); ?>">
		<div class="cnx-repeater__rows">
			<?php foreach ( $blocos as $i => $bloco ) : ?>
				<?php cnx_render_bloco_row( (int) $i, (array) $bloco ); ?>
			<?php endforeach; ?>
		</div>

		<template class="cnx-repeater__template">
			<?php cnx_render_bloco_row( -1, array() ); ?>
		</template>

		<button type="button" class="button button-secondary cnx-repeater__add">
			<?php esc_html_e( '+ Adicionar bloco', 'conexao' ); ?>
		</button>
	</div>
	<?php
}

function cnx_render_bloco_row( int $index, array $bloco ): void {
	$i        = ( -1 === $index ) ? '__INDEX__' : (string) $index;
	$titulo   = (string) ( $bloco['titulo'] ?? '' );
	$conteudo = (string) ( $bloco['conteudo'] ?? '' );
	?>
	<div class="cnx-row">
		<div class="cnx-row__handle" title="<?php esc_attr_e( 'Arraste para reordenar', 'conexao' ); ?>">⠿</div>

		<div class="cnx-row__body">
			<label class="cnx-field">
				<strong><?php esc_html_e( 'Título', 'conexao' ); ?></strong>
				<input type="text" name="cnx_blocos[<?php echo esc_attr( $i ); ?>][titulo]" value="<?php echo esc_attr( $titulo ); ?>" placeholder="<?php esc_attr_e( 'Materiais e acabamentos', 'conexao' ); ?>">
			</label>

			<label class="cnx-field">
				<strong><?php esc_html_e( 'Conteúdo', 'conexao' ); ?></strong>
				<textarea name="cnx_blocos[<?php echo esc_attr( $i ); ?>][conteudo]" rows="5"><?php echo esc_textarea( $conteudo ); ?></textarea>
				<span class="description"><?php esc_html_e( 'HTML simples é permitido (parágrafos, listas, links, negrito).', 'conexao' ); ?></span>
			</label>
		</div>

		<button type="button" class="button-link cnx-row__remove" aria-label="<?php esc_attr_e( 'Remover bloco', 'conexao' ); ?>">✕</button>
	</div>
	<?php
}

function cnx_render_box_galeria( WP_Post $post ): void {
	$ids = cnx_produto_galeria( $post->ID );
	?>
	<div class="cnx-galeria" data-cnx-galeria>
		<ul class="cnx-galeria__list">
			<?php foreach ( $ids as $id ) : ?>
				<li class="cnx-galeria__item" data-id="<?php echo esc_attr( (string) $id ); ?>">
					<?php echo wp_get_attachment_image( $id, 'thumbnail' ); ?>
					<button type="button" class="cnx-galeria__remove" aria-label="<?php esc_attr_e( 'Remover imagem', 'conexao' ); ?>">✕</button>
				</li>
			<?php endforeach; ?>
		</ul>

		<input type="hidden" name="cnx_galeria" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>">

		<button type="button" class="button button-secondary cnx-galeria__add">
			<?php esc_html_e( 'Selecionar imagens', 'conexao' ); ?>
		</button>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Gravação
 * ------------------------------------------------------------------------- */

add_action( 'save_post_' . CNX_CPT_PRODUTO, 'cnx_save_produto_meta', 10, 2 );

function cnx_save_produto_meta( int $post_id, WP_Post $post ): void {
	// Autosave e revisões não trazem os campos do formulário: sair evita apagar dados.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['cnx_produto_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['cnx_produto_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'cnx_save_produto' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// --- Campos simples ---------------------------------------------------

	$texto = array(
		'sku'      => 'sanitize_text_field',
		'prazo'    => 'sanitize_text_field',
		'whatsapp' => 'cnx_sanitize_telefone',
	);

	foreach ( $texto as $key => $sanitizer ) {
		$raw = isset( $_POST[ 'cnx_' . $key ] ) ? wp_unslash( $_POST[ 'cnx_' . $key ] ) : '';
		cnx_update_meta( $post_id, $key, call_user_func( $sanitizer, $raw ) );
	}

	$resumo = isset( $_POST['cnx_resumo'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cnx_resumo'] ) ) : '';
	cnx_update_meta( $post_id, 'resumo', $resumo );

	$qtd_minima = isset( $_POST['cnx_qtd_minima'] ) ? absint( $_POST['cnx_qtd_minima'] ) : 0;
	cnx_update_meta( $post_id, 'qtd_minima', $qtd_minima ? (string) $qtd_minima : '' );

	$fundo = isset( $_POST['cnx_fundo'] ) ? (string) sanitize_hex_color( wp_unslash( $_POST['cnx_fundo'] ) ) : '';
	cnx_update_meta( $post_id, 'fundo', $fundo );

	cnx_update_meta( $post_id, 'destaque', empty( $_POST['cnx_destaque'] ) ? '' : '1' );

	// --- Galeria ----------------------------------------------------------

	$galeria = isset( $_POST['cnx_galeria'] ) ? sanitize_text_field( wp_unslash( $_POST['cnx_galeria'] ) ) : '';
	$galeria = array_filter( array_map( 'absint', explode( ',', $galeria ) ) );
	cnx_update_meta( $post_id, 'galeria', implode( ',', $galeria ) );

	// --- Grupos de configuração ------------------------------------------

	$grupos = array();

	if ( isset( $_POST['cnx_config'] ) && is_array( $_POST['cnx_config'] ) ) {
		foreach ( wp_unslash( $_POST['cnx_config'] ) as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}

			$titulo = sanitize_text_field( (string) ( $raw['titulo'] ?? '' ) );
			$opcoes = cnx_lines_to_array( (string) ( $raw['opcoes'] ?? '' ) );

			// Linha vazia (o usuário clicou em adicionar e não preencheu) é descartada.
			if ( '' === $titulo && empty( $opcoes ) ) {
				continue;
			}

			$grupos[] = array(
				'titulo'      => $titulo,
				'ajuda'       => sanitize_text_field( (string) ( $raw['ajuda'] ?? '' ) ),
				'obrigatorio' => empty( $raw['obrigatorio'] ) ? 0 : 1,
				'opcoes'      => array_map( 'sanitize_text_field', $opcoes ),
			);
		}
	}

	cnx_update_meta( $post_id, 'config_grupos', $grupos );

	// --- Blocos de conteúdo ----------------------------------------------

	$blocos = array();

	if ( isset( $_POST['cnx_blocos'] ) && is_array( $_POST['cnx_blocos'] ) ) {
		foreach ( wp_unslash( $_POST['cnx_blocos'] ) as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}

			$titulo   = sanitize_text_field( (string) ( $raw['titulo'] ?? '' ) );
			$conteudo = wp_kses_post( (string) ( $raw['conteudo'] ?? '' ) );

			if ( '' === $titulo && '' === trim( $conteudo ) ) {
				continue;
			}

			$blocos[] = array(
				'titulo'   => $titulo,
				'conteudo' => $conteudo,
			);
		}
	}

	cnx_update_meta( $post_id, 'blocos', $blocos );
}

/**
 * Grava o meta com prefixo, apagando a chave quando o valor é vazio.
 */
function cnx_update_meta( int $post_id, string $key, mixed $value ): void {
	$key = '_cnx_' . $key;

	if ( '' === $value || array() === $value ) {
		delete_post_meta( $post_id, $key );

		return;
	}

	update_post_meta( $post_id, $key, $value );
}

/**
 * Telefone: guardamos só os dígitos, é o formato que o wa.me espera.
 */
function cnx_sanitize_telefone( string $value ): string {
	return preg_replace( '/\D/', '', $value ) ?? '';
}
