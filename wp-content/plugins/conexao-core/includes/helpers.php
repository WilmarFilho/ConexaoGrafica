<?php
/**
 * Funções utilitárias usadas pelo plugin e pelo tema.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lê um meta do produto já com o prefixo padrão.
 */
function cnx_meta( int $post_id, string $key, mixed $default = '' ): mixed {
	$value = get_post_meta( $post_id, '_cnx_' . $key, true );

	return ( '' === $value || null === $value ) ? $default : $value;
}

/**
 * Transforma um textarea "uma opção por linha" em array limpo.
 */
function cnx_lines_to_array( string $text ): array {
	$lines = preg_split( '/\r\n|\r|\n/', $text );

	if ( ! is_array( $lines ) ) {
		return array();
	}

	$lines = array_map( 'trim', $lines );
	$lines = array_filter( $lines, static fn( $line ) => '' !== $line );

	return array_values( $lines );
}

/**
 * Número de WhatsApp: o do produto tem prioridade, senão cai no global.
 * Formato salvo/retornado: apenas dígitos, com DDI. Ex.: 5521999999999
 */
function cnx_whatsapp_numero( ?int $post_id = null ): string {
	$numero = '';

	if ( $post_id ) {
		$numero = (string) cnx_meta( $post_id, 'whatsapp' );
	}

	if ( '' === $numero ) {
		$numero = (string) get_option( 'cnx_whatsapp_numero', '' );
	}

	return preg_replace( '/\D/', '', $numero ) ?? '';
}

/**
 * Abertura da mensagem do WhatsApp.
 *
 * O default de register_setting() só vale depois do admin_init, que não roda no
 * front — por isso o fallback mora aqui, e não na definição da opção.
 */
function cnx_whatsapp_saudacao(): string {
	$texto = (string) get_option( 'cnx_whatsapp_saudacao', '' );

	return '' !== trim( $texto )
		? $texto
		: __( 'Olá! Vim pelo site e quero um orçamento.', 'conexao' );
}

/**
 * Monta o link wa.me com a mensagem já codificada.
 */
function cnx_whatsapp_link( string $mensagem, ?int $post_id = null ): string {
	$numero = cnx_whatsapp_numero( $post_id );

	if ( '' === $numero ) {
		return '';
	}

	return 'https://wa.me/' . $numero . '?text=' . rawurlencode( $mensagem );
}

/**
 * Grupos de configuração do produto, normalizados para o front.
 *
 * @return array<int, array{titulo:string, ajuda:string, obrigatorio:bool, opcoes:array<int,string>}>
 */
function cnx_produto_configuracao( int $post_id ): array {
	$grupos = cnx_meta( $post_id, 'config_grupos', array() );

	if ( ! is_array( $grupos ) ) {
		return array();
	}

	$out = array();

	foreach ( $grupos as $grupo ) {
		$titulo  = trim( (string) ( $grupo['titulo'] ?? '' ) );
		$opcoes  = is_array( $grupo['opcoes'] ?? null ) ? $grupo['opcoes'] : array();

		// Grupo sem título ou sem opções não renderiza nada útil.
		if ( '' === $titulo || empty( $opcoes ) ) {
			continue;
		}

		$out[] = array(
			'titulo'      => $titulo,
			'ajuda'       => trim( (string) ( $grupo['ajuda'] ?? '' ) ),
			'obrigatorio' => ! empty( $grupo['obrigatorio'] ),
			'opcoes'      => array_values( array_map( 'strval', $opcoes ) ),
		);
	}

	return $out;
}

/**
 * Blocos de conteúdo (accordion) do produto.
 *
 * @return array<int, array{titulo:string, conteudo:string}>
 */
function cnx_produto_blocos( int $post_id ): array {
	$blocos = cnx_meta( $post_id, 'blocos', array() );

	if ( ! is_array( $blocos ) ) {
		return array();
	}

	$out = array();

	foreach ( $blocos as $bloco ) {
		$titulo = trim( (string) ( $bloco['titulo'] ?? '' ) );

		if ( '' === $titulo ) {
			continue;
		}

		$out[] = array(
			'titulo'   => $titulo,
			'conteudo' => (string) ( $bloco['conteudo'] ?? '' ),
		);
	}

	return $out;
}

/**
 * IDs das imagens da galeria do produto.
 *
 * @return array<int, int>
 */
function cnx_produto_galeria( int $post_id ): array {
	$ids = cnx_meta( $post_id, 'galeria', '' );

	if ( ! is_string( $ids ) || '' === $ids ) {
		return array();
	}

	$ids = array_map( 'absint', explode( ',', $ids ) );

	return array_values( array_filter( $ids ) );
}
