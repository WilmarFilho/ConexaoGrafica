<?php
/**
 * CSS/JS do admin — carregados só na tela de edição de produto.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_enqueue_scripts', 'cnx_admin_assets' );

function cnx_admin_assets( string $hook ): void {
	$screen = get_current_screen();

	if ( ! $screen ) {
		return;
	}

	$telas_de_post = in_array( $hook, array( 'post.php', 'post-new.php' ), true )
		&& in_array( $screen->post_type, array( CNX_CPT_PRODUTO, CNX_CPT_SLIDE, CNX_CPT_BANNER ), true );

	// Categorias e Soluções têm campo de imagem: precisam do seletor de mídia.
	$telas_de_termo = in_array( $hook, array( 'term.php', 'edit-tags.php' ), true )
		&& in_array( $screen->taxonomy, array( CNX_TAX_CATEGORIA, CNX_TAX_SOLUCAO ), true );

	if ( ! $telas_de_post && ! $telas_de_termo ) {
		return;
	}

	// A galeria usa o seletor de mídia nativo.
	wp_enqueue_media();

	wp_enqueue_style(
		'cnx-admin',
		CNX_URL . 'assets/admin/admin.css',
		array(),
		CNX_VERSION
	);

	wp_enqueue_script(
		'cnx-admin',
		CNX_URL . 'assets/admin/admin.js',
		array( 'jquery-ui-sortable' ),
		CNX_VERSION,
		true
	);

	wp_localize_script(
		'cnx-admin',
		'cnxAdmin',
		array(
			'galeriaTitulo' => __( 'Imagens do produto', 'conexao' ),
			'galeriaBotao'  => __( 'Usar estas imagens', 'conexao' ),
			'confirmar'     => __( 'Remover este item?', 'conexao' ),
		)
	);
}
