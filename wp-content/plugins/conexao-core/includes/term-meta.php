<?php
/**
 * Campos extras nas taxonomias.
 *
 * Categorias e Soluções já existem como taxonomia desde o início — dar imagem a
 * elas é melhor do que criar um CPT paralelo: o card da home passa a linkar para
 * o arquivo real da categoria, e não há dois lugares cadastrando a mesma coisa.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Campos por taxonomia.
 *
 * tipo: imagem | cor | texto | html | checkbox
 */
function cnx_term_fields( string $taxonomy ): array {
	$campos = array(
		CNX_TAX_CATEGORIA => array(
			'cnx_imagem'   => array(
				'tipo'  => 'imagem',
				'label' => __( 'Imagem do card', 'conexao' ),
				'ajuda' => __( 'Usada na seção "Categorias em destaque" da home.', 'conexao' ),
			),
			'cnx_fundo'    => array(
				'tipo'   => 'cor',
				'label'  => __( 'Cor de fundo do card', 'conexao' ),
				'ajuda'  => __( 'Aparece atrás da imagem nas sobras do enquadramento.', 'conexao' ),
				'padrao' => '#edeceb',
			),
			'cnx_ordem'    => array(
				'tipo'  => 'numero',
				'label' => __( 'Ordem', 'conexao' ),
				'ajuda' => __( 'Menor aparece primeiro. Vazio vai para o fim, em ordem alfabética.', 'conexao' ),
			),
			'cnx_destaque' => array(
				'tipo'  => 'checkbox',
				'label' => __( 'Exibir em "Categorias em destaque"', 'conexao' ),
				'ajuda' => __( 'A home mostra as categorias marcadas aqui, na ordem alfabética.', 'conexao' ),
			),
		),
		CNX_TAX_SOLUCAO   => array(
			'cnx_imagem' => array(
				'tipo'  => 'imagem',
				'label' => __( 'Imagem do card', 'conexao' ),
				'ajuda' => __( 'Arte exibida no card da seção "Soluções".', 'conexao' ),
			),
			'cnx_rotulo' => array(
				'tipo'  => 'html',
				'label' => __( 'Rótulo da tarja colorida', 'conexao' ),
				'ajuda' => __( 'Ex.: Papelaria para &lt;strong&gt;Advogados&lt;/strong&gt;', 'conexao' ),
			),
			'cnx_cor'    => array(
				'tipo'    => 'cor',
				'label'   => __( 'Cor da tarja', 'conexao' ),
				'padrao'  => '#ff6700',
			),
			'cnx_fundo'  => array(
				'tipo'   => 'cor',
				'label'  => __( 'Cor de fundo do card', 'conexao' ),
				'ajuda'  => __( 'Aparece atrás da imagem nas sobras do enquadramento.', 'conexao' ),
				'padrao' => '#e2e2e2',
			),
			'cnx_ordem'  => array(
				'tipo'  => 'numero',
				'label' => __( 'Ordem', 'conexao' ),
				'ajuda' => __( 'Menor aparece primeiro. Vazio vai para o fim, em ordem alfabética.', 'conexao' ),
			),
		),
	);

	return $campos[ $taxonomy ] ?? array();
}

/**
 * Registra os hooks das duas taxonomias.
 */
add_action(
	'admin_init',
	static function (): void {
		foreach ( array( CNX_TAX_CATEGORIA, CNX_TAX_SOLUCAO ) as $taxonomy ) {
			add_action( "{$taxonomy}_add_form_fields", 'cnx_term_add_fields' );
			add_action( "{$taxonomy}_edit_form_fields", 'cnx_term_edit_fields', 10, 2 );
			add_action( "created_{$taxonomy}", 'cnx_save_term_fields' );
			add_action( "edited_{$taxonomy}", 'cnx_save_term_fields' );
		}
	}
);

/**
 * Formulário de criação (colunas empilhadas).
 */
function cnx_term_add_fields( string $taxonomy ): void {
	wp_nonce_field( 'cnx_save_term', 'cnx_term_nonce' );

	foreach ( cnx_term_fields( $taxonomy ) as $chave => $campo ) {
		echo '<div class="form-field">';
		printf( '<label for="%s">%s</label>', esc_attr( $chave ), esc_html( $campo['label'] ) );
		cnx_render_term_field( $chave, $campo, '' );

		if ( ! empty( $campo['ajuda'] ) ) {
			printf( '<p>%s</p>', wp_kses( $campo['ajuda'], array( 'strong' => array() ) ) );
		}

		echo '</div>';
	}
}

/**
 * Formulário de edição (tabela de duas colunas).
 */
function cnx_term_edit_fields( WP_Term $term, string $taxonomy ): void {
	wp_nonce_field( 'cnx_save_term', 'cnx_term_nonce' );

	foreach ( cnx_term_fields( $taxonomy ) as $chave => $campo ) {
		$valor = (string) get_term_meta( $term->term_id, $chave, true );

		echo '<tr class="form-field">';
		printf( '<th scope="row"><label for="%s">%s</label></th>', esc_attr( $chave ), esc_html( $campo['label'] ) );
		echo '<td>';
		cnx_render_term_field( $chave, $campo, $valor );

		if ( ! empty( $campo['ajuda'] ) ) {
			printf( '<p class="description">%s</p>', wp_kses( $campo['ajuda'], array( 'strong' => array() ) ) );
		}

		echo '</td></tr>';
	}
}

function cnx_render_term_field( string $chave, array $campo, string $valor ): void {
	switch ( $campo['tipo'] ) {
		case 'imagem':
			$id = absint( $valor );
			?>
			<div class="cnx-galeria" data-cnx-galeria data-cnx-unica>
				<ul class="cnx-galeria__list">
					<?php if ( $id ) : ?>
						<li class="cnx-galeria__item" data-id="<?php echo esc_attr( (string) $id ); ?>">
							<?php echo wp_get_attachment_image( $id, 'thumbnail' ); ?>
							<button type="button" class="cnx-galeria__remove" aria-label="<?php esc_attr_e( 'Remover imagem', 'conexao' ); ?>">✕</button>
						</li>
					<?php endif; ?>
				</ul>
				<input type="hidden" name="<?php echo esc_attr( $chave ); ?>" value="<?php echo esc_attr( $id ? (string) $id : '' ); ?>">
				<button type="button" class="button button-secondary cnx-galeria__add">
					<?php esc_html_e( 'Selecionar imagem', 'conexao' ); ?>
				</button>
			</div>
			<?php
			break;

		case 'cor':
			printf(
				'<input type="color" id="%1$s" name="%1$s" value="%2$s">',
				esc_attr( $chave ),
				esc_attr( $valor ?: (string) ( $campo['padrao'] ?? '#ff6700' ) )
			);
			break;

		case 'numero':
			printf(
				'<input type="number" id="%1$s" name="%1$s" value="%2$s" min="0" step="1" style="width:90px;">',
				esc_attr( $chave ),
				esc_attr( $valor )
			);
			break;

		case 'checkbox':
			printf(
				'<label><input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s> %3$s</label>',
				esc_attr( $chave ),
				checked( $valor, '1', false ),
				esc_html__( 'Sim', 'conexao' )
			);
			break;

		default: // texto | html
			printf(
				'<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text">',
				esc_attr( $chave ),
				esc_attr( $valor )
			);
	}
}

function cnx_save_term_fields( int $term_id ): void {
	if ( ! isset( $_POST['cnx_term_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cnx_term_nonce'] ) ), 'cnx_save_term' ) ) {
		return;
	}

	$term = get_term( $term_id );

	if ( ! $term instanceof WP_Term ) {
		return;
	}

	$taxonomy = get_taxonomy( $term->taxonomy );

	if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->edit_terms ) ) {
		return;
	}

	foreach ( cnx_term_fields( $term->taxonomy ) as $chave => $campo ) {
		switch ( $campo['tipo'] ) {
			case 'imagem':
				$valor = isset( $_POST[ $chave ] ) ? absint( $_POST[ $chave ] ) : 0;
				$valor = $valor ? (string) $valor : '';
				break;

			case 'cor':
				$valor = isset( $_POST[ $chave ] ) ? sanitize_hex_color( wp_unslash( $_POST[ $chave ] ) ) : '';
				$valor = (string) $valor;
				break;

			case 'checkbox':
				$valor = empty( $_POST[ $chave ] ) ? '' : '1';
				break;

			case 'numero':
				$valor = isset( $_POST[ $chave ] ) && '' !== $_POST[ $chave ]
					? (string) absint( $_POST[ $chave ] )
					: '';
				break;

			case 'html':
				$valor = isset( $_POST[ $chave ] )
					? wp_kses( wp_unslash( $_POST[ $chave ] ), cnx_slide_html_permitido() )
					: '';
				break;

			default:
				$valor = isset( $_POST[ $chave ] ) ? sanitize_text_field( wp_unslash( $_POST[ $chave ] ) ) : '';
		}

		if ( '' === $valor ) {
			delete_term_meta( $term_id, $chave );
		} else {
			update_term_meta( $term_id, $chave, $valor );
		}
	}
}

/**
 * Ordena termos pelo campo "Ordem", com o nome como desempate.
 *
 * A ordenação é feita em PHP e não no get_terms() de propósito: usar meta_key no
 * orderby faz um INNER JOIN, e todo termo sem o campo preenchido sumiria da lista.
 *
 * @param WP_Term[] $termos
 * @return WP_Term[]
 */
function cnx_ordenar_termos( array $termos ): array {
	usort(
		$termos,
		static function ( WP_Term $a, WP_Term $b ): int {
			$ordem_a = get_term_meta( $a->term_id, 'cnx_ordem', true );
			$ordem_b = get_term_meta( $b->term_id, 'cnx_ordem', true );

			// Sem ordem definida vai para o fim.
			$ordem_a = ( '' === $ordem_a ) ? PHP_INT_MAX : (int) $ordem_a;
			$ordem_b = ( '' === $ordem_b ) ? PHP_INT_MAX : (int) $ordem_b;

			return $ordem_a === $ordem_b
				? strcoll( $a->name, $b->name )
				: $ordem_a <=> $ordem_b;
		}
	);

	return $termos;
}

/**
 * Miniatura na listagem de termos, para bater o olho e ver o que falta de imagem.
 */
add_action(
	'admin_init',
	static function (): void {
		foreach ( array( CNX_TAX_CATEGORIA, CNX_TAX_SOLUCAO ) as $taxonomy ) {
			add_filter(
				"manage_edit-{$taxonomy}_columns",
				static function ( array $columns ): array {
					return array_merge(
						array_slice( $columns, 0, 1, true ),
						array( 'cnx_imagem' => __( 'Imagem', 'conexao' ) ),
						array_slice( $columns, 1, null, true )
					);
				}
			);

			add_filter(
				"manage_{$taxonomy}_custom_column",
				static function ( string $saida, string $column, int $term_id ): string {
					if ( 'cnx_imagem' !== $column ) {
						return $saida;
					}

					$id = (int) get_term_meta( $term_id, 'cnx_imagem', true );

					return $id
						? (string) wp_get_attachment_image( $id, array( 50, 50 ), false, array( 'style' => 'border-radius:3px;object-fit:cover;' ) )
						: '<span style="color:#d63638;">—</span>';
				},
				10,
				3
			);
		}
	}
);
