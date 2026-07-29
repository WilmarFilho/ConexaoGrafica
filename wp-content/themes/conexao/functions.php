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

	// Sem crop: as artes aparecem inteiras (object-fit: contain no CSS) e o
	// navegador recebe pixels suficientes para telas com escala de 125%/150%.
	add_image_size( 'cnx-produto', 1200, 1200, false );
	add_image_size( 'cnx-card', 800, 800, false );
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
 * SVG de uma rede social. Fica aqui porque rodapé e blog usam os mesmos ícones.
 */
function cnx_icone_rede( string $rede ): string {
	$formas = array(
		'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1.2" fill="currentColor" stroke="none"/>',
		'facebook'  => '<path d="M14 8.5h2.5V5.5H14c-2 0-3.3 1.4-3.3 3.4v1.8H8.5v3h2.2V21h3v-7.3h2.3l.5-3h-2.8V9.4c0-.6.3-.9.8-.9Z" fill="currentColor" stroke="none"/>',
		'youtube'   => '<rect x="2.5" y="6" width="19" height="12" rx="3.5"/><path d="m10.5 9.5 5 2.5-5 2.5Z" fill="currentColor" stroke="none"/>',
	);

	if ( ! isset( $formas[ $rede ] ) ) {
		return '';
	}

	return '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor"'
		. ' stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
		. $formas[ $rede ] . '</svg>';
}

/**
 * Trilha de navegação. Sai como <nav> com lista ordenada, que é o que leitor de
 * tela e o Google esperam.
 *
 * @param array<int, array{0:string,1:string}> $extras Pares [rótulo, url]; url vazia = item atual.
 */
function cnx_breadcrumb( array $extras = array() ): void {
	$itens = array( array( __( 'Home', 'conexao' ), home_url( '/' ) ) );

	foreach ( $extras as $extra ) {
		$itens[] = $extra;
	}

	$ultimo = count( $itens ) - 1;
	?>
	<nav class="cnx-trilha" aria-label="<?php esc_attr_e( 'Trilha de navegação', 'conexao' ); ?>">
		<ol>
			<?php foreach ( $itens as $i => $item ) : ?>
				<li>
					<?php if ( '' !== $item[1] && $i !== $ultimo ) : ?>
						<a href="<?php echo esc_url( $item[1] ); ?>"><?php echo esc_html( $item[0] ); ?></a>
					<?php else : ?>
						<span aria-current="page"><?php echo esc_html( $item[0] ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</nav>
	<?php
}

/**
 * Primeira categoria de produto de um post, para montar a trilha.
 */
function cnx_categoria_principal( int $post_id ): ?WP_Term {
	$termos = get_the_terms( $post_id, 'cnx_categoria_produto' );

	return ( is_array( $termos ) && ! empty( $termos ) ) ? $termos[0] : null;
}

/**
 * Tempo de leitura em minutos. 200 palavras por minuto é a média usada em
 * pesquisa de legibilidade; abaixo de um minuto arredonda para um.
 */
function cnx_tempo_leitura( int $post_id ): int {
	$texto    = (string) get_post_field( 'post_content', $post_id );
	$palavras = str_word_count( wp_strip_all_tags( strip_shortcodes( $texto ) ) );

	return max( 1, (int) ceil( $palavras / 200 ) );
}

/**
 * Contador de leituras, para a lista "Posts mais lidos".
 *
 * Não conta quem está logado (o autor relendo o próprio texto inflaria o
 * número) nem requisições sem navegador.
 */
add_action( 'wp_head', 'cnx_contar_visualizacao' );

function cnx_contar_visualizacao(): void {
	if ( ! is_singular( 'post' ) || is_user_logged_in() ) {
		return;
	}

	if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
		return;
	}

	$post_id = get_queried_object_id();
	$atual   = (int) get_post_meta( $post_id, '_cnx_visualizacoes', true );

	update_post_meta( $post_id, '_cnx_visualizacoes', $atual + 1 );
}

/**
 * Quantos produtos por página nas listagens de categoria e solução.
 *
 * Doze fecha três linhas de quatro no desktop; o padrão do WordPress (10)
 * deixaria a última linha quebrada.
 */
add_action( 'pre_get_posts', 'cnx_produtos_por_pagina' );

function cnx_produtos_por_pagina( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( $query->is_tax( array( 'cnx_categoria_produto', 'cnx_solucao' ) ) || $query->is_post_type_archive( 'cnx_produto' ) ) {
		$query->set( 'posts_per_page', 12 );
	}
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

	// Listagens de categoria e solução: o "Carregar mais" anexa em vez de navegar.
	if ( is_tax( array( 'cnx_categoria_produto', 'cnx_solucao' ) ) || is_post_type_archive( 'cnx_produto' ) ) {
		wp_enqueue_script(
			'cnx-listagem',
			get_theme_file_uri( 'assets/js/listagem.js' ),
			array(),
			cnx_asset_ver( 'assets/js/listagem.js' ),
			true
		);

		// A listagem também mostra "Produtos relacionados", que é um trilho.
		wp_enqueue_script(
			'cnx-trilho',
			get_theme_file_uri( 'assets/js/trilho.js' ),
			array(),
			cnx_asset_ver( 'assets/js/trilho.js' ),
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
