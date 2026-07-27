<?php
/**
 * Registro dos Custom Post Types.
 *
 * É este arquivo que cria a "aba Produtos" no menu lateral do admin.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'cnx_register_post_types' );

function cnx_register_post_types(): void {

	$labels = array(
		'name'                  => __( 'Produtos', 'conexao' ),
		'singular_name'         => __( 'Produto', 'conexao' ),
		'menu_name'             => __( 'Produtos', 'conexao' ),
		'add_new'               => __( 'Adicionar novo', 'conexao' ),
		'add_new_item'          => __( 'Adicionar novo produto', 'conexao' ),
		'edit_item'             => __( 'Editar produto', 'conexao' ),
		'new_item'              => __( 'Novo produto', 'conexao' ),
		'view_item'             => __( 'Ver produto', 'conexao' ),
		'view_items'            => __( 'Ver produtos', 'conexao' ),
		'search_items'          => __( 'Buscar produtos', 'conexao' ),
		'not_found'             => __( 'Nenhum produto encontrado.', 'conexao' ),
		'not_found_in_trash'    => __( 'Nenhum produto na lixeira.', 'conexao' ),
		'all_items'             => __( 'Todos os produtos', 'conexao' ),
		'archives'              => __( 'Arquivo de produtos', 'conexao' ),
		'featured_image'        => __( 'Imagem principal', 'conexao' ),
		'set_featured_image'    => __( 'Definir imagem principal', 'conexao' ),
		'remove_featured_image' => __( 'Remover imagem principal', 'conexao' ),
		'use_featured_image'    => __( 'Usar como imagem principal', 'conexao' ),
		'item_published'        => __( 'Produto publicado.', 'conexao' ),
		'item_updated'          => __( 'Produto atualizado.', 'conexao' ),
	);

	register_post_type(
		CNX_CPT_PRODUTO,
		array(
			'labels'             => $labels,
			'public'             => true,
			'has_archive'        => 'produtos',
			'menu_position'      => 5, // Logo abaixo de "Posts".
			'menu_icon'          => 'dashicons-products',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes', 'revisions' ),
			'taxonomies'         => array( CNX_TAX_CATEGORIA, CNX_TAX_SOLUCAO ),
			'hierarchical'       => false,
			'show_in_rest'       => true, // Editor de blocos + REST API.
			'rest_base'          => 'produtos',
			'rewrite'            => array(
				'slug'       => 'produtos',
				'with_front' => false,
			),
			'capability_type'    => 'post',
			'show_in_nav_menus'  => true,
			'publicly_queryable' => true,
		)
	);

	/**
	 * Slides do carrossel da home.
	 *
	 * Não é público: um slide não tem página própria nem URL. Ele existe só para
	 * alimentar o shortcode [cnx_hero]. A ordem é o campo "Ordem" (menu_order).
	 */
	register_post_type(
		CNX_CPT_SLIDE,
		array(
			'labels'             => array(
				'name'               => __( 'Slides', 'conexao' ),
				'singular_name'      => __( 'Slide', 'conexao' ),
				'menu_name'          => __( 'Slides', 'conexao' ),
				'add_new'            => __( 'Adicionar novo', 'conexao' ),
				'add_new_item'       => __( 'Adicionar novo slide', 'conexao' ),
				'edit_item'          => __( 'Editar slide', 'conexao' ),
				'all_items'          => __( 'Todos os slides', 'conexao' ),
				'search_items'       => __( 'Buscar slides', 'conexao' ),
				'not_found'          => __( 'Nenhum slide criado ainda.', 'conexao' ),
				'featured_image'     => __( 'Imagem do produto (coluna direita)', 'conexao' ),
				'set_featured_image' => __( 'Definir imagem do produto', 'conexao' ),
			),
			'public'             => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'menu_position'      => 6,
			'menu_icon'          => 'dashicons-images-alt2',
			'supports'           => array( 'title', 'thumbnail', 'page-attributes' ),
			'has_archive'        => false,
			'publicly_queryable' => false,
			'exclude_from_search'=> true,
			'show_in_rest'       => false,
			'capability_type'    => 'post',
		)
	);

	/**
	 * Banners de chamada — a faixa "Mais do que uma gráfica".
	 *
	 * Vira CPT (e não uma opção do site) porque a mesma seção pode aparecer em
	 * páginas diferentes com texto e foto diferentes: [cnx_banner slug="..."].
	 */
	register_post_type(
		CNX_CPT_BANNER,
		array(
			'labels'              => array(
				'name'               => __( 'Banners', 'conexao' ),
				'singular_name'      => __( 'Banner', 'conexao' ),
				'menu_name'          => __( 'Banners', 'conexao' ),
				'add_new'            => __( 'Adicionar novo', 'conexao' ),
				'add_new_item'       => __( 'Adicionar novo banner', 'conexao' ),
				'edit_item'          => __( 'Editar banner', 'conexao' ),
				'all_items'          => __( 'Todos os banners', 'conexao' ),
				'not_found'          => __( 'Nenhum banner criado ainda.', 'conexao' ),
				'featured_image'     => __( 'Foto de fundo', 'conexao' ),
				'set_featured_image' => __( 'Definir foto de fundo', 'conexao' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 7,
			'menu_icon'           => 'dashicons-format-image',
			'supports'            => array( 'title', 'thumbnail' ),
			'has_archive'         => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_in_rest'        => false,
			'capability_type'     => 'post',
		)
	);

	/**
	 * Leads capturados pelo formulário do rodapé.
	 *
	 * Guardar no próprio WordPress evita depender de serviço externo agora; se um
	 * dia entrar Mailchimp/RD, o histórico continua aqui.
	 */
	register_post_type(
		CNX_CPT_LEAD,
		array(
			'labels'              => array(
				'name'          => __( 'Leads', 'conexao' ),
				'singular_name' => __( 'Lead', 'conexao' ),
				'menu_name'     => __( 'Leads', 'conexao' ),
				'all_items'     => __( 'Todos os leads', 'conexao' ),
				'edit_item'     => __( 'Ver lead', 'conexao' ),
				'search_items'  => __( 'Buscar leads', 'conexao' ),
				'not_found'     => __( 'Nenhum lead recebido ainda.', 'conexao' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 26,
			'menu_icon'           => 'dashicons-email-alt',
			'supports'            => array( 'title' ),
			'has_archive'         => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_in_rest'        => false,
			'capability_type'     => 'post',
			'capabilities'        => array( 'create_posts' => 'do_not_allow' ), // Só o formulário cria.
			'map_meta_cap'        => true,
		)
	);
}

/**
 * Mensagens de admin no idioma do projeto (o WP usa "Post" genérico por padrão).
 */
add_filter( 'post_updated_messages', 'cnx_produto_updated_messages' );

function cnx_produto_updated_messages( array $messages ): array {
	global $post;

	$permalink = get_permalink( $post );

	$messages[ CNX_CPT_PRODUTO ] = array(
		0  => '',
		1  => sprintf( __( 'Produto atualizado. <a href="%s">Ver produto</a>', 'conexao' ), esc_url( $permalink ) ),
		4  => __( 'Produto atualizado.', 'conexao' ),
		6  => sprintf( __( 'Produto publicado. <a href="%s">Ver produto</a>', 'conexao' ), esc_url( $permalink ) ),
		7  => __( 'Produto salvo.', 'conexao' ),
		8  => __( 'Produto enviado.', 'conexao' ),
		10 => __( 'Rascunho do produto atualizado.', 'conexao' ),
	);

	return $messages;
}
