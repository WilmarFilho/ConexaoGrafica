<?php
/**
 * Captura de leads do formulário do rodapé.
 *
 * O envio vai para admin-post.php (e não para a própria página) porque assim o
 * processamento acontece antes de qualquer HTML sair, permitindo redirecionar
 * com PRG — recarregar a página não reenvia o formulário.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Opções do select "Tipo de serviço".
 *
 * Vêm das categorias de produto: a lista se mantém sozinha conforme o catálogo
 * cresce, em vez de virar um array esquecido no código.
 *
 * @return array<int, string>
 */
function cnx_tipos_de_servico(): array {
	$termos = get_terms(
		array(
			'taxonomy'   => CNX_TAX_CATEGORIA,
			'parent'     => 0,
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);

	$nomes = is_wp_error( $termos ) ? array() : wp_list_pluck( cnx_ordenar_termos( $termos ), 'name' );

	$nomes[] = __( 'Outro', 'conexao' );

	return apply_filters( 'cnx_tipos_de_servico', $nomes );
}

add_action( 'admin_post_nopriv_cnx_lead', 'cnx_processar_lead' );
add_action( 'admin_post_cnx_lead', 'cnx_processar_lead' );

function cnx_processar_lead(): void {
	$retorno = wp_get_referer() ?: home_url( '/' );

	if ( ! isset( $_POST['cnx_lead_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cnx_lead_nonce'] ) ), 'cnx_lead' ) ) {
		cnx_redirecionar_lead( $retorno, 'erro' );
	}

	// Honeypot: campo escondido no CSS. Se veio preenchido, é robô.
	if ( ! empty( $_POST['cnx_site'] ) ) {
		cnx_redirecionar_lead( $retorno, 'ok' ); // Silencioso de propósito.
	}

	$email = isset( $_POST['cnx_email'] ) ? sanitize_email( wp_unslash( $_POST['cnx_email'] ) ) : '';
	$tipo  = isset( $_POST['cnx_tipo'] ) ? sanitize_text_field( wp_unslash( $_POST['cnx_tipo'] ) ) : '';

	if ( ! is_email( $email ) ) {
		cnx_redirecionar_lead( $retorno, 'email' );
	}

	// Só aceita um valor que realmente está no select.
	if ( '' !== $tipo && ! in_array( $tipo, cnx_tipos_de_servico(), true ) ) {
		$tipo = '';
	}

	// Mesmo e-mail duas vezes não vira dois leads.
	$existente = get_posts(
		array(
			'post_type'   => CNX_CPT_LEAD,
			'post_status' => 'any',
			'numberposts' => 1,
			'title'       => $email,
			'fields'      => 'ids',
		)
	);

	if ( ! empty( $existente ) ) {
		cnx_redirecionar_lead( $retorno, 'ok' );
	}

	$lead_id = wp_insert_post(
		array(
			'post_type'   => CNX_CPT_LEAD,
			'post_status' => 'publish',
			'post_title'  => $email,
		),
		true
	);

	if ( is_wp_error( $lead_id ) ) {
		cnx_redirecionar_lead( $retorno, 'erro' );
	}

	update_post_meta( $lead_id, '_cnx_lead_tipo', $tipo );
	update_post_meta( $lead_id, '_cnx_lead_origem', esc_url_raw( $retorno ) );

	cnx_notificar_lead( $email, $tipo );

	cnx_redirecionar_lead( $retorno, 'ok' );
}

/**
 * Volta para a página de origem, na âncora do formulário, com o status na URL.
 */
function cnx_redirecionar_lead( string $url, string $status, string $ancora = 'cnx-desconto' ): void {
	wp_safe_redirect( add_query_arg( 'cnx_lead', $status, $url ) . '#' . $ancora );
	exit;
}

function cnx_notificar_lead( string $email, string $tipo ): void {
	$destino = (string) get_option( 'cnx_email_comercial', '' );

	if ( '' === $destino ) {
		$destino = (string) get_option( 'admin_email', '' );
	}

    if ( ! is_email( $destino ) ) {
		return;
	}

	$assunto = sprintf(
		/* translators: %s: nome do site */
		__( '[%s] Novo pedido de desconto', 'conexao' ),
		wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES )
	);

	$corpo = sprintf(
		"%s\n\nE-mail: %s\nTipo de serviço: %s\n",
		__( 'Um visitante pediu o desconto de primeiro pedido pelo rodapé do site.', 'conexao' ),
		$email,
		'' !== $tipo ? $tipo : __( '(não informado)', 'conexao' )
	);

	wp_mail( $destino, $assunto, $corpo );
}

/* -------------------------------------------------------------------------
 * Orçamento do produto e mensagem de contato
 *
 * Os dois gravam um lead antes de qualquer coisa: se o WhatsApp não abrir ou o
 * e-mail falhar, o contato não se perde.
 * ------------------------------------------------------------------------- */

add_action( 'admin_post_nopriv_cnx_orcamento', 'cnx_processar_orcamento' );
add_action( 'admin_post_cnx_orcamento', 'cnx_processar_orcamento' );

function cnx_processar_orcamento(): void {
	$retorno = wp_get_referer() ?: home_url( '/' );

	if ( ! isset( $_POST['cnx_orcamento_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cnx_orcamento_nonce'] ) ), 'cnx_orcamento' ) ) {
		cnx_redirecionar_lead( $retorno, 'erro' );
	}

	if ( ! empty( $_POST['cnx_site'] ) ) {
		cnx_redirecionar_lead( $retorno, 'ok' );
	}

	$nome     = isset( $_POST['cnx_nome'] ) ? sanitize_text_field( wp_unslash( $_POST['cnx_nome'] ) ) : '';
	$telefone = isset( $_POST['cnx_telefone'] ) ? cnx_sanitize_telefone( (string) wp_unslash( $_POST['cnx_telefone'] ) ) : '';
	$email    = isset( $_POST['cnx_email'] ) ? sanitize_email( wp_unslash( $_POST['cnx_email'] ) ) : '';
	$produto  = isset( $_POST['cnx_produto_id'] ) ? absint( $_POST['cnx_produto_id'] ) : 0;
	$resumo   = isset( $_POST['cnx_resumo'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cnx_resumo'] ) ) : '';

	if ( '' === $nome || '' === $telefone ) {
		cnx_redirecionar_lead( $retorno, 'dados' );
	}

	cnx_registrar_lead(
		array(
			'titulo'   => '' !== $email ? $email : $nome,
			'nome'     => $nome,
			'telefone' => $telefone,
			'email'    => $email,
			'produto'  => $produto,
			'mensagem' => $resumo,
			'origem'   => 'orcamento',
			'url'      => $retorno,
		)
	);

	// A mensagem do WhatsApp é montada no servidor: o cliente não escolhe o texto.
	$linhas = array( cnx_whatsapp_saudacao(), '' );

	if ( $produto ) {
		$linhas[] = 'Produto: ' . get_the_title( $produto );
	}

	if ( '' !== $resumo ) {
		$linhas[] = $resumo;
	}

	$linhas[] = '';
	$linhas[] = 'Nome: ' . $nome;
	$linhas[] = 'Telefone: ' . $telefone;

	if ( '' !== $email ) {
		$linhas[] = 'E-mail: ' . $email;
	}

	if ( $produto ) {
		$linhas[] = '';
		$linhas[] = (string) get_permalink( $produto );
	}

	$link = cnx_whatsapp_link( implode( "\n", $linhas ), $produto ?: null );

	// Sem número configurado não há para onde mandar: volta com aviso de sucesso,
	// já que o lead ficou gravado.
	wp_safe_redirect( '' !== $link ? $link : add_query_arg( 'cnx_lead', 'ok', $retorno ) );
	exit;
}

add_action( 'admin_post_nopriv_cnx_mensagem', 'cnx_processar_mensagem' );
add_action( 'admin_post_cnx_mensagem', 'cnx_processar_mensagem' );

function cnx_processar_mensagem(): void {
	$retorno = wp_get_referer() ?: home_url( '/contato/' );

	if ( ! isset( $_POST['cnx_mensagem_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cnx_mensagem_nonce'] ) ), 'cnx_mensagem' ) ) {
		cnx_redirecionar_lead( $retorno, 'erro', 'cnx-contato' );
	}

	if ( ! empty( $_POST['cnx_site'] ) ) {
		cnx_redirecionar_lead( $retorno, 'ok', 'cnx-contato' );
	}

	$nome     = isset( $_POST['cnx_nome'] ) ? sanitize_text_field( wp_unslash( $_POST['cnx_nome'] ) ) : '';
	$email    = isset( $_POST['cnx_email'] ) ? sanitize_email( wp_unslash( $_POST['cnx_email'] ) ) : '';
	$telefone = isset( $_POST['cnx_telefone'] ) ? cnx_sanitize_telefone( (string) wp_unslash( $_POST['cnx_telefone'] ) ) : '';
	$tipo     = isset( $_POST['cnx_tipo'] ) ? sanitize_text_field( wp_unslash( $_POST['cnx_tipo'] ) ) : '';
	$mensagem = isset( $_POST['cnx_mensagem'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cnx_mensagem'] ) ) : '';

	if ( '' === $nome || ! is_email( $email ) ) {
		cnx_redirecionar_lead( $retorno, 'dados', 'cnx-contato' );
	}

	if ( '' !== $tipo && ! in_array( $tipo, cnx_tipos_de_servico(), true ) ) {
		$tipo = '';
	}

	cnx_registrar_lead(
		array(
			'titulo'   => $email,
			'nome'     => $nome,
			'telefone' => $telefone,
			'email'    => $email,
			'tipo'     => $tipo,
			'mensagem' => $mensagem,
			'origem'   => 'contato',
			'url'      => $retorno,
		)
	);

	cnx_redirecionar_lead( $retorno, 'ok', 'cnx-contato' );
}

/**
 * Grava o lead e notifica. Um só lugar para as duas origens.
 */
function cnx_registrar_lead( array $dados ): int {
	$lead_id = wp_insert_post(
		array(
			'post_type'   => CNX_CPT_LEAD,
			'post_status' => 'publish',
			'post_title'  => (string) ( $dados['titulo'] ?? '' ),
		),
		true
	);

	if ( is_wp_error( $lead_id ) ) {
		return 0;
	}

	$mapa = array(
		'nome'     => '_cnx_lead_nome',
		'telefone' => '_cnx_lead_telefone',
		'email'    => '_cnx_lead_email',
		'tipo'     => '_cnx_lead_tipo',
		'mensagem' => '_cnx_lead_mensagem',
		'origem'   => '_cnx_lead_origem_form',
		'produto'  => '_cnx_lead_produto',
	);

	foreach ( $mapa as $chave => $meta ) {
		$valor = $dados[ $chave ] ?? '';

		if ( '' !== $valor && 0 !== $valor ) {
			update_post_meta( $lead_id, $meta, $valor );
		}
	}

	update_post_meta( $lead_id, '_cnx_lead_origem', esc_url_raw( (string) ( $dados['url'] ?? '' ) ) );

	cnx_notificar_contato( $dados );

	return (int) $lead_id;
}

function cnx_notificar_contato( array $dados ): void {
	$destino = (string) get_option( 'cnx_email_comercial', '' ) ?: (string) get_option( 'admin_email', '' );

	if ( ! is_email( $destino ) ) {
		return;
	}

	$origem = 'orcamento' === ( $dados['origem'] ?? '' )
		? __( 'Pedido de orçamento', 'conexao' )
		: __( 'Mensagem de contato', 'conexao' );

	$corpo = array( $origem, '' );

	foreach ( array(
		__( 'Nome', 'conexao' )     => $dados['nome'] ?? '',
		__( 'Telefone', 'conexao' ) => $dados['telefone'] ?? '',
		__( 'E-mail', 'conexao' )   => $dados['email'] ?? '',
		__( 'Serviço', 'conexao' )  => $dados['tipo'] ?? '',
	) as $rotulo => $valor ) {
		if ( '' !== $valor ) {
			$corpo[] = $rotulo . ': ' . $valor;
		}
	}

	if ( ! empty( $dados['produto'] ) ) {
		$corpo[] = __( 'Produto', 'conexao' ) . ': ' . get_the_title( (int) $dados['produto'] );
	}

	if ( '' !== ( $dados['mensagem'] ?? '' ) ) {
		$corpo[] = '';
		$corpo[] = (string) $dados['mensagem'];
	}

	wp_mail(
		$destino,
		sprintf( '[%1$s] %2$s', wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ), $origem ),
		implode( "\n", $corpo )
	);
}

/* -------------------------------------------------------------------------
 * Listagem no admin
 * ------------------------------------------------------------------------- */

add_filter( 'manage_' . CNX_CPT_LEAD . '_posts_columns', 'cnx_lead_columns' );

function cnx_lead_columns( array $columns ): array {
	return array(
		'cb'          => $columns['cb'] ?? '',
		'title'       => __( 'Contato', 'conexao' ),
		'cnx_origem'  => __( 'Origem', 'conexao' ),
		'cnx_dados'   => __( 'Dados', 'conexao' ),
		'cnx_tipo'    => __( 'Tipo de serviço', 'conexao' ),
		'cnx_pagina'  => __( 'Veio de', 'conexao' ),
		'date'        => __( 'Recebido em', 'conexao' ),
	);
}

add_action( 'manage_' . CNX_CPT_LEAD . '_posts_custom_column', 'cnx_lead_column_content', 10, 2 );

function cnx_lead_column_content( string $column, int $post_id ): void {
	switch ( $column ) {
		case 'cnx_origem':
			$origens = array(
				'orcamento' => __( 'Orçamento de produto', 'conexao' ),
				'contato'   => __( 'Formulário de contato', 'conexao' ),
			);
			$origem  = (string) get_post_meta( $post_id, '_cnx_lead_origem_form', true );

			echo esc_html( $origens[ $origem ] ?? __( 'Desconto do rodapé', 'conexao' ) );

			$produto = (int) get_post_meta( $post_id, '_cnx_lead_produto', true );

			if ( $produto ) {
				printf( '<br><small>%s</small>', esc_html( get_the_title( $produto ) ) );
			}
			break;

		case 'cnx_dados':
			$linhas = array();

			foreach ( array( '_cnx_lead_nome', '_cnx_lead_telefone' ) as $meta ) {
				$valor = (string) get_post_meta( $post_id, $meta, true );

				if ( '' !== $valor ) {
					$linhas[] = esc_html( $valor );
				}
			}

			$mensagem = (string) get_post_meta( $post_id, '_cnx_lead_mensagem', true );

			if ( '' !== $mensagem ) {
				$linhas[] = '<small>' . esc_html( wp_trim_words( $mensagem, 14 ) ) . '</small>';
			}

			echo empty( $linhas ) ? '—' : wp_kses_post( implode( '<br>', $linhas ) );
			break;

		case 'cnx_tipo':
			$tipo = (string) get_post_meta( $post_id, '_cnx_lead_tipo', true );
			echo '' !== $tipo ? esc_html( $tipo ) : '—';
			break;

		case 'cnx_pagina':
			$origem = (string) get_post_meta( $post_id, '_cnx_lead_origem', true );

			if ( '' === $origem ) {
				echo '—';
				break;
			}

			printf(
				'<a href="%s" target="_blank" rel="noopener">%s</a>',
				esc_url( $origem ),
				esc_html( (string) wp_parse_url( $origem, PHP_URL_PATH ) ?: '/' )
			);
			break;
	}
}

/**
 * Exportar a lista para CSV — é o que se pede na hora de disparar a campanha.
 */
add_action( 'admin_post_cnx_exportar_leads', 'cnx_exportar_leads' );

function cnx_exportar_leads(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sem permissão.', 'conexao' ) );
	}

	check_admin_referer( 'cnx_exportar_leads' );

	$leads = get_posts(
		array(
			'post_type'   => CNX_CPT_LEAD,
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'date',
			'order'       => 'DESC',
		)
	);

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=leads-' . gmdate( 'Y-m-d' ) . '.csv' );

	$saida = fopen( 'php://output', 'w' );

	// BOM para o Excel abrir os acentos corretamente.
	fwrite( $saida, "\xEF\xBB\xBF" );
	fputcsv( $saida, array( 'E-mail', 'Tipo de serviço', 'Data' ) );

	foreach ( $leads as $lead ) {
		fputcsv(
			$saida,
			array(
				$lead->post_title,
				(string) get_post_meta( $lead->ID, '_cnx_lead_tipo', true ),
				get_the_date( 'Y-m-d H:i', $lead ),
			)
		);
	}

	fclose( $saida );
	exit;
}

add_action( 'restrict_manage_posts', 'cnx_botao_exportar_leads' );

function cnx_botao_exportar_leads( string $post_type ): void {
	if ( CNX_CPT_LEAD !== $post_type || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	printf(
		'<a class="button" href="%s">%s</a>',
		esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cnx_exportar_leads' ), 'cnx_exportar_leads' ) ),
		esc_html__( 'Exportar CSV', 'conexao' )
	);
}
