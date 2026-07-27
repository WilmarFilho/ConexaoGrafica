<?php
/**
 * Taxonomias dos produtos.
 *
 * Categoria  -> hierárquica  (Cartão de Visita e Papelaria > Cartão de Visita)
 * Solução    -> hierárquica  (Advogados, Laboratórios, Consultórios, Editoriais)
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'cnx_register_taxonomies' );

function cnx_register_taxonomies(): void {

	register_taxonomy(
		CNX_TAX_CATEGORIA,
		array( CNX_CPT_PRODUTO ),
		array(
			'labels'            => array(
				'name'              => __( 'Categorias', 'conexao' ),
				'singular_name'     => __( 'Categoria', 'conexao' ),
				'menu_name'         => __( 'Categorias', 'conexao' ),
				'all_items'         => __( 'Todas as categorias', 'conexao' ),
				'edit_item'         => __( 'Editar categoria', 'conexao' ),
				'add_new_item'      => __( 'Adicionar nova categoria', 'conexao' ),
				'search_items'      => __( 'Buscar categorias', 'conexao' ),
				'parent_item'       => __( 'Categoria mãe', 'conexao' ),
				'parent_item_colon' => __( 'Categoria mãe:', 'conexao' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rest_base'         => 'categorias-produto',
			'rewrite'           => array(
				'slug'         => 'categoria',
				'with_front'   => false,
				'hierarchical' => true,
			),
		)
	);

	register_taxonomy(
		CNX_TAX_SOLUCAO,
		array( CNX_CPT_PRODUTO ),
		array(
			'labels'            => array(
				'name'          => __( 'Soluções', 'conexao' ),
				'singular_name' => __( 'Solução', 'conexao' ),
				'menu_name'     => __( 'Soluções', 'conexao' ),
				'all_items'     => __( 'Todas as soluções', 'conexao' ),
				'edit_item'     => __( 'Editar solução', 'conexao' ),
				'add_new_item'  => __( 'Adicionar nova solução', 'conexao' ),
				'search_items'  => __( 'Buscar soluções', 'conexao' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rest_base'         => 'solucoes',
			'rewrite'           => array(
				'slug'       => 'solucao',
				'with_front' => false,
			),
		)
	);
}
