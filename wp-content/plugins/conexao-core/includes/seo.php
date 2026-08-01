<?php
/**
 * SEO sem plugin externo: título e descrição configuráveis (home e termos),
 * canonical, Open Graph e JSON-LD.
 *
 * Tudo sai pelos filtros nativos ('document_title_parts', 'wp_head'), então
 * qualquer plugin de SEO instalado no futuro pode assumir sem conflito — basta
 * desligar este módulo.
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Fontes de dados
 * ------------------------------------------------------------------------- */

/**
 * Título e descrição do contexto atual, com os fallbacks sensatos.
 *
 * @return array{titulo:string, descricao:string, url:string, imagem:string, tipo:string}
 */
function cnx_seo_contexto(): array {
	$padrao_desc = (string) get_option( 'cnx_sobre_curto', '' );
	$dados       = array(
		'titulo'    => '',
		'descricao' => $padrao_desc,
		'url'       => home_url( add_query_arg( array() ) ),
		'imagem'    => '',
		'tipo'      => 'website',
	);

	if ( is_front_page() ) {
		$dados['titulo']    = (string) get_option( 'cnx_seo_titulo', '' );
		$dados['descricao'] = (string) get_option( 'cnx_seo_descricao', '' ) ?: $padrao_desc;
		$dados['url']       = home_url( '/' );

		return $dados;
	}

	if ( is_tax( array( CNX_TAX_CATEGORIA, CNX_TAX_SOLUCAO ) ) ) {
		$termo = get_queried_object();

		if ( $termo instanceof WP_Term ) {
			$dados['titulo']    = (string) get_term_meta( $termo->term_id, 'cnx_seo_titulo', true );
			$dados['descricao'] = (string) get_term_meta( $termo->term_id, 'cnx_seo_descricao', true )
				?: ( $termo->description ?: $padrao_desc );
			$dados['url']       = (string) get_term_link( $termo );

			$imagem_id = (int) get_term_meta( $termo->term_id, 'cnx_imagem', true );

			if ( $imagem_id ) {
				$dados['imagem'] = (string) wp_get_attachment_image_url( $imagem_id, 'large' );
			}
		}

		return $dados;
	}

	if ( is_singular() ) {
		$post_id            = get_queried_object_id();
		$dados['url']       = (string) get_permalink( $post_id );
		$dados['tipo']      = is_singular( 'post' ) ? 'article' : 'website';
		$dados['descricao'] = has_excerpt( $post_id )
			? (string) get_the_excerpt( $post_id )
			: ( (string) cnx_meta( $post_id, 'resumo' ) ?: $padrao_desc );

		if ( has_post_thumbnail( $post_id ) ) {
			$dados['imagem'] = (string) get_the_post_thumbnail_url( $post_id, 'large' );
		}
	}

	return $dados;
}

/* -------------------------------------------------------------------------
 * Título do documento
 * ------------------------------------------------------------------------- */

add_filter( 'document_title_parts', 'cnx_seo_titulo_documento' );

function cnx_seo_titulo_documento( array $partes ): array {
	$contexto = cnx_seo_contexto();

	if ( '' !== $contexto['titulo'] ) {
		$partes['title'] = $contexto['titulo'];

		// Título sob medida já vem completo: sem o "– Nome do site" automático.
		unset( $partes['site'], $partes['tagline'] );
	}

	return $partes;
}

/* -------------------------------------------------------------------------
 * Meta tags
 * ------------------------------------------------------------------------- */

add_action( 'wp_head', 'cnx_seo_meta_tags', 4 );

function cnx_seo_meta_tags(): void {
	$contexto  = cnx_seo_contexto();
	$descricao = trim( wp_strip_all_tags( $contexto['descricao'] ) );
	$titulo    = wp_get_document_title();

	if ( '' !== $descricao ) {
		printf( "<meta name=\"description\" content=\"%s\">\n", esc_attr( wp_html_excerpt( $descricao, 160, '…' ) ) );
	}

	// O core só emite canonical em conteúdo singular; home e termos ficam sem.
	if ( ! is_singular() ) {
		printf( "<link rel=\"canonical\" href=\"%s\">\n", esc_url( $contexto['url'] ) );
	}

	printf( "<meta property=\"og:type\" content=\"%s\">\n", esc_attr( $contexto['tipo'] ) );
	printf( "<meta property=\"og:title\" content=\"%s\">\n", esc_attr( $titulo ) );
	printf( "<meta property=\"og:url\" content=\"%s\">\n", esc_url( $contexto['url'] ) );
	printf( "<meta property=\"og:site_name\" content=\"%s\">\n", esc_attr( get_bloginfo( 'name' ) ) );
	printf( "<meta property=\"og:locale\" content=\"pt_BR\">\n" );

	if ( '' !== $descricao ) {
		printf( "<meta property=\"og:description\" content=\"%s\">\n", esc_attr( wp_html_excerpt( $descricao, 200, '…' ) ) );
	}

	if ( '' !== $contexto['imagem'] ) {
		printf( "<meta property=\"og:image\" content=\"%s\">\n", esc_url( $contexto['imagem'] ) );
	}

	printf( "<meta name=\"twitter:card\" content=\"%s\">\n", '' !== $contexto['imagem'] ? 'summary_large_image' : 'summary' );
}

/* -------------------------------------------------------------------------
 * Sitemap
 * ------------------------------------------------------------------------- */

/**
 * O sitemap de usuários lista o arquivo de autor — e com ele o login do
 * administrador, de presente para ataques de força bruta. Fora.
 */
add_filter(
	'wp_sitemaps_add_provider',
	static function ( $provider, string $nome ) {
		return 'users' === $nome ? false : $provider;
	},
	10,
	2
);

/* -------------------------------------------------------------------------
 * JSON-LD
 * ------------------------------------------------------------------------- */

add_action( 'wp_head', 'cnx_seo_json_ld', 5 );

function cnx_seo_json_ld(): void {
	$grafo = array();

	if ( is_front_page() ) {
		$organizacao = array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		);

		$telefone = (string) get_option( 'cnx_telefone_1', '' );

		if ( '' !== $telefone ) {
			$organizacao['contactPoint'] = array(
				'@type'       => 'ContactPoint',
				'telephone'   => $telefone,
				'contactType' => 'sales',
			);
		}

		$logo_id = (int) get_theme_mod( 'custom_logo' );

		if ( $logo_id ) {
			$organizacao['logo'] = (string) wp_get_attachment_image_url( $logo_id, 'full' );
		}

		$grafo[] = $organizacao;
	}

	if ( is_tax( array( CNX_TAX_CATEGORIA, CNX_TAX_SOLUCAO ) ) ) {
		$termo = get_queried_object();

		if ( $termo instanceof WP_Term ) {
			$itens = array();

			// A lista de produtos da própria página vira ItemList estruturado.
			foreach ( $GLOBALS['wp_query']->posts as $posicao => $produto ) {
				$itens[] = array(
					'@type'    => 'ListItem',
					'position' => $posicao + 1,
					'name'     => get_the_title( $produto ),
					'url'      => (string) get_permalink( $produto ),
				);
			}

			$grafo[] = array(
				'@type'       => 'CollectionPage',
				'name'        => $termo->name,
				'url'         => (string) get_term_link( $termo ),
				'description' => $termo->description,
				'mainEntity'  => array(
					'@type'           => 'ItemList',
					'numberOfItems'   => count( $itens ),
					'itemListElement' => $itens,
				),
			);

			$grafo[] = array(
				'@type'           => 'BreadcrumbList',
				'itemListElement' => array(
					array(
						'@type'    => 'ListItem',
						'position' => 1,
						'name'     => __( 'Home', 'conexao' ),
						'item'     => home_url( '/' ),
					),
					array(
						'@type'    => 'ListItem',
						'position' => 2,
						'name'     => $termo->name,
						'item'     => (string) get_term_link( $termo ),
					),
				),
			);
		}
	}

	if ( is_singular( CNX_CPT_PRODUTO ) ) {
		$produto_id = get_queried_object_id();
		$categoria  = function_exists( 'cnx_categoria_principal' ) ? cnx_categoria_principal( $produto_id ) : null;

		$produto = array(
			'@type'       => 'Product',
			'name'        => get_the_title( $produto_id ),
			'url'         => (string) get_permalink( $produto_id ),
			'description' => (string) cnx_meta( $produto_id, 'resumo' ),
			'brand'       => array(
				'@type' => 'Brand',
				'name'  => get_bloginfo( 'name' ),
			),
		);

		if ( has_post_thumbnail( $produto_id ) ) {
			$produto['image'] = (string) get_the_post_thumbnail_url( $produto_id, 'large' );
		}

		if ( $categoria instanceof WP_Term ) {
			$produto['category'] = $categoria->name;
		}

		$grafo[] = $produto;

		$trilha = array(
			array(
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => __( 'Home', 'conexao' ),
				'item'     => home_url( '/' ),
			),
		);

		if ( $categoria instanceof WP_Term ) {
			$trilha[] = array(
				'@type'    => 'ListItem',
				'position' => 2,
				'name'     => $categoria->name,
				'item'     => (string) get_term_link( $categoria ),
			);
		}

		$trilha[] = array(
			'@type'    => 'ListItem',
			'position' => count( $trilha ) + 1,
			'name'     => get_the_title( $produto_id ),
			'item'     => (string) get_permalink( $produto_id ),
		);

		$grafo[] = array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $trilha,
		);
	}

	if ( is_singular( 'post' ) ) {
		$post_id = get_queried_object_id();

		$artigo = array(
			'@type'         => 'BlogPosting',
			'headline'      => get_the_title( $post_id ),
			'url'           => (string) get_permalink( $post_id ),
			'datePublished' => (string) get_the_date( 'c', $post_id ),
			'dateModified'  => (string) get_the_modified_date( 'c', $post_id ),
			'author'        => array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) ),
			),
			'publisher'     => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
			),
		);

		if ( has_post_thumbnail( $post_id ) ) {
			$artigo['image'] = (string) get_the_post_thumbnail_url( $post_id, 'large' );
		}

		$grafo[] = $artigo;

		$blog_id = (int) get_option( 'page_for_posts' );
		$trilha  = array(
			array(
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => __( 'Home', 'conexao' ),
				'item'     => home_url( '/' ),
			),
		);

		if ( $blog_id ) {
			$trilha[] = array(
				'@type'    => 'ListItem',
				'position' => 2,
				'name'     => get_the_title( $blog_id ),
				'item'     => (string) get_permalink( $blog_id ),
			);
		}

		$trilha[] = array(
			'@type'    => 'ListItem',
			'position' => count( $trilha ) + 1,
			'name'     => get_the_title( $post_id ),
			'item'     => (string) get_permalink( $post_id ),
		);

		$grafo[] = array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $trilha,
		);
	}

	if ( empty( $grafo ) ) {
		return;
	}

	printf(
		"<script type=\"application/ld+json\">%s</script>\n",
		wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $grafo,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		)
	);
}
