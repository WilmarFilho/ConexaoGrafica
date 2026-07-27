<?php
/**
 * Colunas e filtros da listagem de produtos no admin.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'manage_' . CNX_CPT_PRODUTO . '_posts_columns', 'cnx_produto_columns' );

function cnx_produto_columns( array $columns ): array {
	$novo = array();

	foreach ( $columns as $key => $label ) {
		if ( 'title' === $key ) {
			$novo['cnx_thumb'] = __( 'Imagem', 'conexao' );
		}

		$novo[ $key ] = $label;

		if ( 'title' === $key ) {
			$novo['cnx_sku']      = __( 'SKU', 'conexao' );
			$novo['cnx_opcoes']   = __( 'Configuração', 'conexao' );
			$novo['cnx_destaque'] = __( 'Destaque', 'conexao' );
		}
	}

	return $novo;
}

add_action( 'manage_' . CNX_CPT_PRODUTO . '_posts_custom_column', 'cnx_produto_column_content', 10, 2 );

function cnx_produto_column_content( string $column, int $post_id ): void {
	switch ( $column ) {
		case 'cnx_thumb':
			if ( has_post_thumbnail( $post_id ) ) {
				echo get_the_post_thumbnail( $post_id, array( 60, 60 ), array( 'style' => 'border-radius:4px;object-fit:cover;' ) );
			} else {
				echo '<span class="dashicons dashicons-format-image" style="color:#c3c4c7;font-size:32px;"></span>';
			}
			break;

		case 'cnx_sku':
			$sku = cnx_meta( $post_id, 'sku' );
			echo $sku ? '<code>' . esc_html( $sku ) . '</code>' : '—';
			break;

		case 'cnx_opcoes':
			$grupos = cnx_produto_configuracao( $post_id );

			if ( empty( $grupos ) ) {
				echo '<span style="color:#d63638;">' . esc_html__( 'sem grupos', 'conexao' ) . '</span>';
				break;
			}

			$titulos = wp_list_pluck( $grupos, 'titulo' );
			echo esc_html( implode( ' · ', $titulos ) );
			break;

		case 'cnx_destaque':
			echo '1' === cnx_meta( $post_id, 'destaque' )
				? '<span class="dashicons dashicons-star-filled" style="color:#f0873f;"></span>'
				: '—';
			break;
	}
}

/**
 * Permite ordenar por SKU.
 */
add_filter( 'manage_edit-' . CNX_CPT_PRODUTO . '_sortable_columns', 'cnx_produto_sortable_columns' );

function cnx_produto_sortable_columns( array $columns ): array {
	$columns['cnx_sku'] = 'cnx_sku';

	return $columns;
}

add_action( 'pre_get_posts', 'cnx_produto_orderby' );

function cnx_produto_orderby( WP_Query $query ): void {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( 'cnx_sku' !== $query->get( 'orderby' ) ) {
		return;
	}

	$query->set( 'meta_key', '_cnx_sku' );
	$query->set( 'orderby', 'meta_value' );
}

/**
 * Filtro por categoria acima da listagem.
 */
add_action( 'restrict_manage_posts', 'cnx_produto_filtro_categoria' );

function cnx_produto_filtro_categoria( string $post_type ): void {
	if ( CNX_CPT_PRODUTO !== $post_type ) {
		return;
	}

	$selecionado = isset( $_GET[ CNX_TAX_CATEGORIA ] ) ? sanitize_text_field( wp_unslash( $_GET[ CNX_TAX_CATEGORIA ] ) ) : '';

	wp_dropdown_categories(
		array(
			'show_option_all' => __( 'Todas as categorias', 'conexao' ),
			'taxonomy'        => CNX_TAX_CATEGORIA,
			'name'            => CNX_TAX_CATEGORIA,
			'value_field'     => 'slug',
			'selected'        => $selecionado,
			'hierarchical'    => true,
			'hide_empty'      => false,
			'orderby'         => 'name',
		)
	);
}
