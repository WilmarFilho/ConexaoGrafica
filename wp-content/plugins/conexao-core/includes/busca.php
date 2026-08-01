<?php
/**
 * Busca do site: escopo, filtros e dados da tela de resultados.
 *
 * O design trata a busca como vitrine de produtos, com filtros por categoria,
 * acabamento, tipo de papel e faixa de quantidade. Acabamento e papel são
 * taxonomias próprias (editáveis no admin); a faixa de quantidade deriva do
 * campo "quantidade mínima" que o produto já tem.
 */

defined( 'ABSPATH' ) || exit;

define( 'CNX_TAX_ACABAMENTO', 'cnx_acabamento' );
define( 'CNX_TAX_PAPEL', 'cnx_papel' );

add_action( 'init', 'cnx_register_taxonomias_busca' );

function cnx_register_taxonomias_busca(): void {
	$comum = array(
		'public'            => false,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'hierarchical'      => true, // Checkboxes no editor, não campo de tags.
	);

	register_taxonomy(
		CNX_TAX_ACABAMENTO,
		array( CNX_CPT_PRODUTO ),
		$comum + array(
			'labels' => array(
				'name'          => __( 'Acabamentos', 'conexao' ),
				'singular_name' => __( 'Acabamento', 'conexao' ),
				'menu_name'     => __( 'Acabamentos', 'conexao' ),
				'add_new_item'  => __( 'Adicionar acabamento', 'conexao' ),
			),
		)
	);

	register_taxonomy(
		CNX_TAX_PAPEL,
		array( CNX_CPT_PRODUTO ),
		$comum + array(
			'labels' => array(
				'name'          => __( 'Tipos de papel', 'conexao' ),
				'singular_name' => __( 'Tipo de papel', 'conexao' ),
				'menu_name'     => __( 'Tipos de papel', 'conexao' ),
				'add_new_item'  => __( 'Adicionar tipo de papel', 'conexao' ),
			),
		)
	);
}

/**
 * Faixas de quantidade mínima oferecidas no filtro.
 *
 * @return array<string, array{rotulo:string, min:int, max:int}>
 */
function cnx_faixas_quantidade(): array {
	return array(
		'ate-500'   => array( 'rotulo' => __( '100–500 unidades', 'conexao' ), 'min' => 0, 'max' => 500 ),
		'500-1000'  => array( 'rotulo' => __( '500–1.000 unidades', 'conexao' ), 'min' => 500, 'max' => 1000 ),
		'1000-2000' => array( 'rotulo' => __( '1.000–2.000 unidades', 'conexao' ), 'min' => 1000, 'max' => 2000 ),
		'2000-mais' => array( 'rotulo' => __( 'Acima de 2.000', 'conexao' ), 'min' => 2000, 'max' => PHP_INT_MAX ),
	);
}

/**
 * Filtros marcados na URL, já saneados.
 *
 * @return array{categorias:array, acabamentos:array, papeis:array, qtd:array}
 */
function cnx_filtros_da_busca(): array {
	$lista = static function ( string $chave ): array {
		if ( ! isset( $_GET[ $chave ] ) || ! is_array( $_GET[ $chave ] ) ) {
			return array();
		}

		return array_filter( array_map( 'sanitize_title', wp_unslash( $_GET[ $chave ] ) ) );
	};

	return array(
		'categorias'  => $lista( 'categoria' ),
		'acabamentos' => $lista( 'acabamento' ),
		'papeis'      => $lista( 'papel' ),
		'qtd'         => array_intersect( $lista( 'qtd' ), array_keys( cnx_faixas_quantidade() ) ),
	);
}

/**
 * A busca principal vira vitrine de produtos, como no design; o blog tem a sua
 * própria busca restrita (post_type=post no formulário da lateral).
 */
add_action( 'pre_get_posts', 'cnx_busca_escopo' );

function cnx_busca_escopo( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}

	// A lateral do blog manda post_type=post e mantém a busca de artigos.
	if ( 'post' === $query->get( 'post_type' ) ) {
		return;
	}

	$query->set( 'post_type', CNX_CPT_PRODUTO );
	$query->set( 'posts_per_page', 12 );

	$filtros   = cnx_filtros_da_busca();
	$tax_query = array();

	foreach ( array(
		CNX_TAX_CATEGORIA  => $filtros['categorias'],
		CNX_TAX_ACABAMENTO => $filtros['acabamentos'],
		CNX_TAX_PAPEL      => $filtros['papeis'],
	) as $taxonomia => $slugs ) {
		if ( ! empty( $slugs ) ) {
			$tax_query[] = array(
				'taxonomy' => $taxonomia,
				'field'    => 'slug',
				'terms'    => $slugs,
			);
		}
	}

	if ( ! empty( $tax_query ) ) {
		$tax_query['relation'] = 'AND';
		$query->set( 'tax_query', $tax_query );
	}

	if ( ! empty( $filtros['qtd'] ) ) {
		$faixas     = cnx_faixas_quantidade();
		$meta_query = array( 'relation' => 'OR' );

		foreach ( $filtros['qtd'] as $chave ) {
			$meta_query[] = array(
				'key'     => '_cnx_qtd_minima',
				'value'   => array( $faixas[ $chave ]['min'], $faixas[ $chave ]['max'] ),
				'type'    => 'NUMERIC',
				'compare' => 'BETWEEN',
			);
		}

		$query->set( 'meta_query', $meta_query );
	}

	// Ordenação do seletor "Mais relevantes".
	$ordem = isset( $_GET['ordem'] ) ? sanitize_key( wp_unslash( $_GET['ordem'] ) ) : '';

	if ( 'nome' === $ordem ) {
		$query->set( 'orderby', 'title' );
		$query->set( 'order', 'ASC' );
	} elseif ( 'recentes' === $ordem ) {
		$query->set( 'orderby', 'date' );
		$query->set( 'order', 'DESC' );
	}
}

/**
 * Soluções cujo nome bate com o termo — entram na contagem "produtos e
 * soluções" e nas buscas relacionadas.
 *
 * @return WP_Term[]
 */
function cnx_solucoes_da_busca( string $termo ): array {
	if ( '' === trim( $termo ) ) {
		return array();
	}

	$termos = get_terms(
		array(
			'taxonomy'   => CNX_TAX_SOLUCAO,
			'hide_empty' => false,
			'search'     => $termo,
		)
	);

	return is_wp_error( $termos ) ? array() : $termos;
}

/**
 * Sugestões de "Buscas relacionadas": nomes de outros produtos das mesmas
 * categorias dos resultados, que viram novos termos de busca.
 *
 * @param int[] $ids_encontrados
 * @return string[]
 */
function cnx_buscas_relacionadas( array $ids_encontrados, int $limite = 4 ): array {
	if ( empty( $ids_encontrados ) ) {
		return array();
	}

	$categorias = array();

	foreach ( array_slice( $ids_encontrados, 0, 4 ) as $id ) {
		foreach ( (array) get_the_terms( $id, CNX_TAX_CATEGORIA ) as $termo ) {
			if ( $termo instanceof WP_Term ) {
				$categorias[ $termo->term_id ] = $termo->term_id;
			}
		}
	}

	if ( empty( $categorias ) ) {
		return array();
	}

	$relacionados = get_posts(
		array(
			'post_type'      => CNX_CPT_PRODUTO,
			'post_status'    => 'publish',
			'posts_per_page' => $limite,
			'post__not_in'   => $ids_encontrados,
			'orderby'        => 'rand',
			'tax_query'      => array(
				array(
					'taxonomy' => CNX_TAX_CATEGORIA,
					'field'    => 'term_id',
					'terms'    => array_values( $categorias ),
				),
			),
		)
	);

	return array_map( 'get_the_title', $relacionados );
}
