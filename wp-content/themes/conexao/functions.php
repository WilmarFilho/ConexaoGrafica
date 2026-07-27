<?php
/**
 * Bootstrap do tema.
 */

defined( 'ABSPATH' ) || exit;

define( 'CNX_THEME_VERSION', '0.1.0' );

add_action( 'after_setup_theme', 'cnx_theme_setup' );

function cnx_theme_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'custom-logo' );

	register_nav_menus(
		array(
			'topo'       => __( 'Topbar (faixa preta)', 'conexao' ),
			'categorias' => __( 'Categorias do header', 'conexao' ),
			'principal'  => __( 'Menu principal (mobile)', 'conexao' ),
			'rodape'     => __( 'Menu do rodapé', 'conexao' ),
			'legal'      => __( 'Rodapé — links legais', 'conexao' ),
		)
	);

	add_image_size( 'cnx-produto', 900, 700, true );
	add_image_size( 'cnx-card', 480, 380, true );
}

/**
 * Links da topbar enquanto nenhum menu foi criado em Aparência → Menus.
 *
 * Evita a topbar nascer vazia numa instalação limpa. Assim que o menu "Topbar"
 * for criado e atribuído, o WordPress ignora este fallback.
 */
function cnx_topbar_menu_fallback( array $args = array() ): void {
	$pagina_posts = (int) get_option( 'page_for_posts' );

	$links = array(
		__( 'Produtos', 'conexao' ) => get_post_type_archive_link( 'cnx_produto' ),
		__( 'Soluções', 'conexao' ) => home_url( '/solucoes/' ),
		__( 'Blog', 'conexao' )     => $pagina_posts ? get_permalink( $pagina_posts ) : home_url( '/blog/' ),
		__( 'Contato', 'conexao' )  => home_url( '/contato/' ),
	);

	// A topbar e o rodapé usam a mesma lista com classes diferentes.
	$classe = (string) ( $args['menu_class'] ?? 'cnx-topbar__menu' );

	printf( '<ul class="%s">', esc_attr( $classe ) );

	foreach ( $links as $rotulo => $url ) {
		if ( ! $url ) {
			continue;
		}

		printf(
			'<li><a href="%s">%s</a></li>',
			esc_url( $url ),
			esc_html( $rotulo )
		);
	}

	echo '</ul>';
}

/**
 * Versão de um asset a partir da data de modificação do arquivo.
 *
 * Sem isso o navegador segura style.css e os scripts em cache entre uma edição e
 * outra — dá para passar horas depurando um CSS que o browser nem baixou.
 */
function cnx_asset_ver( string $relativo ): string {
	$caminho = get_theme_file_path( $relativo );

	return file_exists( $caminho )
		? (string) filemtime( $caminho )
		: CNX_THEME_VERSION;
}

add_action( 'wp_enqueue_scripts', 'cnx_theme_assets' );

function cnx_theme_assets(): void {
	wp_enqueue_style( 'cnx-app', get_stylesheet_uri(), array(), cnx_asset_ver( 'style.css' ) );

	wp_enqueue_script(
		'cnx-app',
		get_theme_file_uri( 'assets/js/app.js' ),
		array(),
		cnx_asset_ver( 'assets/js/app.js' ),
		true
	);

	if ( is_singular( 'cnx_produto' ) ) {
		wp_enqueue_script(
			'cnx-produto',
			get_theme_file_uri( 'assets/js/produto.js' ),
			array(),
			cnx_asset_ver( 'assets/js/produto.js' ),
			true
		);
	}

	// Cada script só é baixado na página que realmente usa o shortcode.
	if ( cnx_pagina_usa_shortcode( 'cnx_hero' ) ) {
		wp_enqueue_script(
			'cnx-carrossel',
			get_theme_file_uri( 'assets/js/carrossel.js' ),
			array(),
			cnx_asset_ver( 'assets/js/carrossel.js' ),
			true
		);
	}

	// Todas as seções em grade viram carrossel no mobile e usam o mesmo trilho.
	$secoes_com_trilho = array( 'cnx_mais_vendidos', 'cnx_categorias', 'cnx_solucoes', 'cnx_diferenciais' );

	foreach ( $secoes_com_trilho as $shortcode ) {
		if ( ! cnx_pagina_usa_shortcode( $shortcode ) ) {
			continue;
		}

		wp_enqueue_script(
			'cnx-trilho',
			get_theme_file_uri( 'assets/js/trilho.js' ),
			array(),
			cnx_asset_ver( 'assets/js/trilho.js' ),
			true
		);

		break;
	}
}

/**
 * Links legais do rodapé enquanto o menu não existe.
 *
 * São páginas que toda loja precisa ter; deixá-las visíveis desde o começo
 * evita esquecer de criá-las.
 */
function cnx_legal_menu_fallback( array $args = array() ): void {
	$links = array(
		__( 'Política de Privacidade', 'conexao' ) => home_url( '/politica-de-privacidade/' ),
		__( 'Termos de uso', 'conexao' )           => home_url( '/termos-de-uso/' ),
		__( 'Política de Cookies', 'conexao' )     => home_url( '/politica-de-cookies/' ),
	);

	printf( '<ul class="%s">', esc_attr( (string) ( $args['menu_class'] ?? 'cnx-rodape__legal' ) ) );

	foreach ( $links as $rotulo => $url ) {
		printf( '<li><a href="%s">%s</a></li>', esc_url( $url ), esc_html( $rotulo ) );
	}

	echo '</ul>';
}

/**
 * Imagem de card com placeholder.
 *
 * As fotos entram no admin aos poucos; até lá a seção mostra uma caixa neutra
 * em vez de um buraco no layout ou um <img> quebrado.
 */
function cnx_figura( int $attachment_id, string $tamanho = 'cnx-card', string $classe = '' ): void {
	if ( $attachment_id && wp_attachment_is_image( $attachment_id ) ) {
		echo wp_get_attachment_image(
			$attachment_id,
			$tamanho,
			false,
			array(
				'class'    => $classe,
				'loading'  => 'lazy',
				'decoding' => 'async',
				'alt'      => '',
			)
		);

		return;
	}

	printf(
		'<span class="cnx-placeholder %s" aria-hidden="true"></span>',
		esc_attr( $classe )
	);
}

/**
 * A página atual contém o shortcode informado?
 */
function cnx_pagina_usa_shortcode( string $shortcode ): bool {
	if ( ! is_singular() ) {
		return false;
	}

	$post = get_post();

	return $post instanceof WP_Post && has_shortcode( $post->post_content, $shortcode );
}
