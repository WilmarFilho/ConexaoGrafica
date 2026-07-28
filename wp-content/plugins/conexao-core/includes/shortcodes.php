<?php
/**
 * Shortcodes das seções reutilizáveis.
 *
 * Páginas do WordPress não executam PHP: o que se cola no editor é o shortcode,
 * e o HTML mora aqui (ou num partial do tema, quando é só apresentação).
 *
 *   [cnx_hero]           carrossel da home
 *   [cnx_diferenciais]   faixa de 4 selos abaixo do carrossel
 */

defined( 'ABSPATH' ) || exit;

/**
 * Slides publicados, na ordem definida em "Ordem" (Atributos do slide).
 *
 * @return array<int, WP_Post>
 */
function cnx_get_slides( int $limite = 10 ): array {
	$slides = get_posts(
		array(
			'post_type'        => CNX_CPT_SLIDE,
			'post_status'      => 'publish',
			'numberposts'      => $limite,
			'orderby'          => array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
			'suppress_filters' => false,
		)
	);

	return is_array( $slides ) ? $slides : array();
}

/**
 * [cnx_hero]
 *
 * Atributos:
 *   limite    quantos slides no máximo (padrão 10)
 *   autoplay  segundos entre slides; 0 desliga (padrão 6)
 */
add_shortcode( 'cnx_hero', 'cnx_shortcode_hero' );

function cnx_shortcode_hero( array|string $atts = array() ): string {
	$atts = shortcode_atts(
		array(
			'limite'   => 10,
			'autoplay' => 6,
		),
		$atts,
		'cnx_hero'
	);

	$slides = cnx_get_slides( (int) $atts['limite'] );

	if ( empty( $slides ) ) {
		return cnx_aviso_admin(
			__( 'Nenhum slide publicado. Crie slides no menu "Slides" do painel.', 'conexao' )
		);
	}

	ob_start();

	// O tema desenha; o plugin só entrega os dados.
	get_template_part(
		'template-parts/sections/hero',
		null,
		array(
			'slides'   => $slides,
			'autoplay' => max( 0, (int) $atts['autoplay'] ),
		)
	);

	return (string) ob_get_clean();
}

/**
 * [cnx_diferenciais]
 *
 * Os quatro selos mudam muito raramente — ficam num array filtrável em vez de
 * mais uma tela no admin. Para alterar: filtro 'cnx_diferenciais'.
 */
add_shortcode( 'cnx_diferenciais', 'cnx_shortcode_diferenciais' );

function cnx_shortcode_diferenciais(): string {
	$itens = apply_filters(
		'cnx_diferenciais',
		array(
			array(
				'icone'  => 'raio',
				'titulo' => __( 'Qualidade garantida', 'conexao' ),
				'texto'  => __( 'Impressão e acabamento com alto padrão em cada detalhe.', 'conexao' ),
			),
			array(
				'icone'  => 'check',
				'titulo' => __( 'Produção rápida', 'conexao' ),
				'texto'  => __( 'Agilidade na produção para atender seus prazos com eficiência.', 'conexao' ),
			),
			array(
				'icone'  => 'balao',
				'titulo' => __( 'Atendimento humano', 'conexao' ),
				'texto'  => __( 'Nossa equipe acompanha você em todas as etapas do pedido.', 'conexao' ),
			),
			array(
				'icone'  => 'caminhao',
				'titulo' => __( 'Entrega nacional', 'conexao' ),
				'texto'  => __( 'Enviamos seus materiais com segurança para todo o Brasil.', 'conexao' ),
			),
		)
	);

	if ( empty( $itens ) ) {
		return '';
	}

	ob_start();

	get_template_part( 'template-parts/sections/diferenciais', null, array( 'itens' => $itens ) );

	return (string) ob_get_clean();
}

/**
 * [cnx_categorias] — grade de categorias marcadas como destaque.
 */
add_shortcode( 'cnx_categorias', 'cnx_shortcode_categorias' );

function cnx_shortcode_categorias( array|string $atts = array() ): string {
	$atts = shortcode_atts(
		array(
			'titulo' => __( 'Categorias em destaque', 'conexao' ),
			'limite' => 4,
		),
		$atts,
		'cnx_categorias'
	);

	$termos = get_terms(
		array(
			'taxonomy'   => CNX_TAX_CATEGORIA,
			'hide_empty' => false,
			'orderby'    => 'name',
			'meta_query' => array(
				array(
					'key'   => 'cnx_destaque',
					'value' => '1',
				),
			),
		)
	);

	if ( ! is_wp_error( $termos ) ) {
		$termos = array_slice( cnx_ordenar_termos( $termos ), 0, (int) $atts['limite'] );
	}

	if ( is_wp_error( $termos ) || empty( $termos ) ) {
		return cnx_aviso_admin(
			__( 'Nenhuma categoria marcada como destaque. Marque a opção "Exibir em Categorias em destaque" em Produtos → Categorias.', 'conexao' )
		);
	}

	ob_start();

	get_template_part(
		'template-parts/sections/categorias',
		null,
		array(
			'titulo' => (string) $atts['titulo'],
			'termos' => $termos,
		)
	);

	return (string) ob_get_clean();
}

/**
 * [cnx_solucoes] — cards das soluções por segmento.
 */
add_shortcode( 'cnx_solucoes', 'cnx_shortcode_solucoes' );

function cnx_shortcode_solucoes( array|string $atts = array() ): string {
	$atts = shortcode_atts(
		array(
			'titulo' => __( 'Soluções', 'conexao' ),
			'limite' => 4,
		),
		$atts,
		'cnx_solucoes'
	);

	$termos = get_terms(
		array(
			'taxonomy'   => CNX_TAX_SOLUCAO,
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);

	if ( ! is_wp_error( $termos ) ) {
		$termos = array_slice( cnx_ordenar_termos( $termos ), 0, (int) $atts['limite'] );
	}

	if ( is_wp_error( $termos ) || empty( $termos ) ) {
		return cnx_aviso_admin(
			__( 'Nenhuma solução cadastrada. Crie termos em Produtos → Soluções.', 'conexao' )
		);
	}

	ob_start();

	get_template_part(
		'template-parts/sections/solucoes',
		null,
		array(
			'titulo' => (string) $atts['titulo'],
			'termos' => $termos,
		)
	);

	return (string) ob_get_clean();
}

/**
 * [cnx_mais_vendidos] — carrossel de produtos marcados como destaque.
 *
 * Atributos: titulo, limite, categoria (slug).
 */
add_shortcode( 'cnx_mais_vendidos', 'cnx_shortcode_mais_vendidos' );

function cnx_shortcode_mais_vendidos( array|string $atts = array() ): string {
	$atts = shortcode_atts(
		array(
			'titulo'    => __( 'Mais vendidos', 'conexao' ),
			'limite'    => 12,
			'categoria' => '',
			'destaque'  => 'sim',
		),
		$atts,
		'cnx_mais_vendidos'
	);

	$consulta = array(
		'post_type'      => CNX_CPT_PRODUTO,
		'post_status'    => 'publish',
		'posts_per_page' => (int) $atts['limite'],
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
	);

	if ( 'sim' === $atts['destaque'] ) {
		$consulta['meta_key']   = '_cnx_destaque';
		$consulta['meta_value'] = '1';
	}

	if ( '' !== $atts['categoria'] ) {
		$consulta['tax_query'] = array(
			array(
				'taxonomy' => CNX_TAX_CATEGORIA,
				'field'    => 'slug',
				'terms'    => array_map( 'trim', explode( ',', (string) $atts['categoria'] ) ),
			),
		);
	}

	$produtos = get_posts( $consulta );

	if ( empty( $produtos ) ) {
		return cnx_aviso_admin(
			__( 'Nenhum produto marcado como destaque. Marque "Exibir em Mais vendidos" ao editar um produto.', 'conexao' )
		);
	}

	ob_start();

	get_template_part(
		'template-parts/sections/mais-vendidos',
		null,
		array(
			'titulo'   => (string) $atts['titulo'],
			'produtos' => $produtos,
		)
	);

	return (string) ob_get_clean();
}

/**
 * [cnx_banner slug="..."] — faixa de chamada com foto de fundo.
 */
add_shortcode( 'cnx_banner', 'cnx_shortcode_banner' );

function cnx_shortcode_banner( array|string $atts = array() ): string {
	$atts = shortcode_atts( array( 'slug' => '' ), $atts, 'cnx_banner' );

	$banners = get_posts(
		array(
			'post_type'   => CNX_CPT_BANNER,
			'post_status' => 'publish',
			'numberposts' => 1,
			'name'        => (string) $atts['slug'], // Vazio = pega o mais recente.
		)
	);

	if ( empty( $banners ) ) {
		return cnx_aviso_admin(
			'' === $atts['slug']
				? __( 'Nenhum banner publicado. Crie um no menu "Banners".', 'conexao' )
				: sprintf(
					/* translators: %s: slug informado no shortcode */
					__( 'Banner "%s" não encontrado. Confira o slug no menu "Banners".', 'conexao' ),
					$atts['slug']
				)
		);
	}

	ob_start();

	get_template_part( 'template-parts/sections/banner', null, array( 'banner' => $banners[0] ) );

	return (string) ob_get_clean();
}

/**
 * [cnx_como_funciona] — as quatro etapas do processo.
 *
 * Como os diferenciais: texto que muda quase nunca vive num array filtrável,
 * não numa tela de admin. Filtro: 'cnx_como_funciona'.
 */
add_shortcode( 'cnx_como_funciona', 'cnx_shortcode_como_funciona' );

function cnx_shortcode_como_funciona( array|string $atts = array() ): string {
	$atts = shortcode_atts( array( 'titulo' => __( 'Como Funciona', 'conexao' ) ), $atts, 'cnx_como_funciona' );

	$etapas = apply_filters(
		'cnx_como_funciona',
		array(
			array( 'icone' => 'documento', 'titulo' => __( 'Solicite', 'conexao' ) ),
			array( 'icone' => 'aprovado', 'titulo' => __( 'Aprove', 'conexao' ) ),
			array( 'icone' => 'impressora', 'titulo' => __( 'Produzimos', 'conexao' ) ),
			array( 'icone' => 'entrega', 'titulo' => __( 'Entregamos', 'conexao' ) ),
		)
	);

	if ( empty( $etapas ) ) {
		return '';
	}

	ob_start();

	get_template_part(
		'template-parts/sections/como-funciona',
		null,
		array(
			'titulo' => (string) $atts['titulo'],
			'etapas' => $etapas,
		)
	);

	return (string) ob_get_clean();
}

/**
 * [cnx_contato] — canais de atendimento, todos vindos de Configurações → Conexão.
 *
 * Usado nas páginas Contato e Solicitar Orçamento: nenhum telefone fica
 * cravado em página nenhuma.
 */
add_shortcode( 'cnx_contato', 'cnx_shortcode_contato' );

function cnx_shortcode_contato(): string {
	$dados = array(
		'whatsapp' => cnx_whatsapp_link( cnx_whatsapp_saudacao() ),
		'horario'  => (string) get_option( 'cnx_horario', '' ),
		'tel1'     => (string) get_option( 'cnx_telefone_1', '' ),
		'tel2'     => (string) get_option( 'cnx_telefone_2', '' ),
		'email'    => (string) get_option( 'cnx_email_comercial', '' ),
	);

	if ( '' === implode( '', $dados ) ) {
		return cnx_aviso_admin(
			__( 'Nenhum canal de contato configurado. Preencha em Configurações → Conexão.', 'conexao' )
		);
	}

	ob_start();

	get_template_part( 'template-parts/sections/contato', null, $dados );

	return (string) ob_get_clean();
}

/**
 * Aviso visível só para quem pode editar — o visitante nunca vê seção quebrada.
 */
function cnx_aviso_admin( string $mensagem ): string {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return '';
	}

	return '<p style="margin:20px auto;max-width:1180px;padding:14px 18px;border-left:3px solid #ff6700;background:#fff8f2;color:#5c4433;font-size:14px;">'
		. esc_html( $mensagem )
		. '</p>';
}
