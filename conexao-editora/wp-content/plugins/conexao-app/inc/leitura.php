<?php
/**
 * Progresso de leitura e marcadores, sincronizados entre os aparelhos.
 * Toda consulta filtra pelo usuário do token: ninguém lê nem apaga o de outro.
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_APP_MAX_PAGINAS = 100000;
const CONEXAO_APP_MAX_MARCADORES = 1000; // por livro

function conexao_app_leitura_linha(int $usuario_id, int $produto_id): ?object
{
    global $wpdb;

    if (! $usuario_id) {
        return null;
    }

    return $wpdb->get_row($wpdb->prepare(
        'SELECT produto_id, pagina, total_paginas, atualizado_em FROM '.conexao_app_tabela('leitura').'
         WHERE user_id = %d AND produto_id = %d',
        $usuario_id,
        $produto_id
    )) ?: null;
}

/** Todas as leituras do usuário, por produto (uma consulta para a estante inteira). */
function conexao_app_leituras_do_usuario(int $usuario_id): array
{
    global $wpdb;

    $linhas = (array) $wpdb->get_results($wpdb->prepare(
        'SELECT produto_id, pagina, total_paginas, atualizado_em FROM '.conexao_app_tabela('leitura').' WHERE user_id = %d',
        $usuario_id
    ));

    $mapa = [];

    foreach ($linhas as $linha) {
        $mapa[(int) $linha->produto_id] = $linha;
    }

    return $mapa;
}

function conexao_app_marcadores(int $usuario_id, int $produto_id): array
{
    global $wpdb;

    $linhas = (array) $wpdb->get_results($wpdb->prepare(
        'SELECT id, pagina, criado_em FROM '.conexao_app_tabela('marcadores').'
         WHERE user_id = %d AND produto_id = %d ORDER BY pagina ASC',
        $usuario_id,
        $produto_id
    ));

    return array_map('conexao_app_marcador', $linhas);
}

function conexao_app_marcador(object $linha): array
{
    return ['id' => (int) $linha->id, 'pagina' => (int) $linha->pagina, 'criado_em' => conexao_app_iso((string) $linha->criado_em)];
}

/** Corpo de GET/PUT /leitura. Sem leitura registrada, página e total vêm null. */
function conexao_app_leitura(int $usuario_id, int $produto_id): array
{
    $linha = conexao_app_leitura_linha($usuario_id, $produto_id);

    return [
        'pagina' => $linha ? (int) $linha->pagina : null,
        'total_paginas' => $linha ? (int) $linha->total_paginas : null,
        'atualizado_em' => $linha ? conexao_app_iso((string) $linha->atualizado_em) : null,
        'marcadores' => conexao_app_marcadores($usuario_id, $produto_id),
    ];
}

/** Inteiro positivo vindo do JSON (aceita "12" e 12; recusa 12.5, -1, "abc"). */
function conexao_app_inteiro($valor): ?int
{
    if (is_int($valor)) {
        return $valor;
    }

    if (is_string($valor) && preg_match('/^\d{1,9}$/', $valor)) {
        return (int) $valor;
    }

    if (is_float($valor) && floor($valor) === $valor) {
        return (int) $valor;
    }

    return null;
}

/** Produto da rota com direito de leitura; senão o erro (404 ou 403). */
function conexao_app_livro_com_acesso(WP_REST_Request $request)
{
    $produto_id = conexao_app_produto_da_rota($request);

    if (is_wp_error($produto_id)) {
        return $produto_id;
    }

    if (! conexao_biblioteca_pode_ler(get_current_user_id(), $produto_id)) {
        return conexao_app_erro('sem_acesso');
    }

    return $produto_id;
}

// ---------------------------------------------------------------- rotas

/** GET /livros/{id}/leitura */
function conexao_app_rota_leitura(WP_REST_Request $request)
{
    $produto_id = conexao_app_livro_com_acesso($request);

    return is_wp_error($produto_id) ? $produto_id : conexao_app_leitura(get_current_user_id(), $produto_id);
}

/** PUT /livros/{id}/leitura {pagina, total_paginas} */
function conexao_app_rota_salva_leitura(WP_REST_Request $request)
{
    global $wpdb;

    $produto_id = conexao_app_livro_com_acesso($request);

    if (is_wp_error($produto_id)) {
        return $produto_id;
    }

    $pagina = conexao_app_inteiro($request->get_param('pagina'));
    $total = conexao_app_inteiro($request->get_param('total_paginas'));
    $campos = [];

    if ($total === null || $total < 1 || $total > CONEXAO_APP_MAX_PAGINAS) {
        $campos['total_paginas'] = 'Total de páginas inválido.';
    }

    if ($pagina === null || $pagina < 1 || ($total !== null && $pagina > $total)) {
        $campos['pagina'] = 'Página inválida.';
    }

    if ($campos) {
        return conexao_app_invalido($campos);
    }

    $usuario_id = get_current_user_id();

    $wpdb->query($wpdb->prepare(
        'INSERT INTO '.conexao_app_tabela('leitura').' (user_id, produto_id, pagina, total_paginas, atualizado_em)
         VALUES (%d, %d, %d, %d, %s)
         ON DUPLICATE KEY UPDATE pagina = VALUES(pagina), total_paginas = VALUES(total_paginas), atualizado_em = VALUES(atualizado_em)',
        $usuario_id,
        $produto_id,
        $pagina,
        $total,
        conexao_app_agora()
    ));

    return conexao_app_leitura($usuario_id, $produto_id);
}

/** POST /livros/{id}/marcadores {pagina}: marcar a mesma página de novo devolve o marcador existente. */
function conexao_app_rota_marca(WP_REST_Request $request)
{
    global $wpdb;

    $produto_id = conexao_app_livro_com_acesso($request);

    if (is_wp_error($produto_id)) {
        return $produto_id;
    }

    $usuario_id = get_current_user_id();
    $pagina = conexao_app_inteiro($request->get_param('pagina'));
    $leitura = conexao_app_leitura_linha($usuario_id, $produto_id);
    $limite = $leitura && (int) $leitura->total_paginas > 0 ? (int) $leitura->total_paginas : CONEXAO_APP_MAX_PAGINAS;

    if ($pagina === null || $pagina < 1 || $pagina > $limite) {
        return conexao_app_invalido(['pagina' => 'Página inválida.']);
    }

    $tabela = conexao_app_tabela('marcadores');
    $consulta = $wpdb->prepare("SELECT id, pagina, criado_em FROM $tabela WHERE user_id = %d AND produto_id = %d AND pagina = %d", $usuario_id, $produto_id, $pagina);
    $existente = $wpdb->get_row($consulta);

    if ($existente) {
        return new WP_REST_Response(conexao_app_marcador($existente), 200);
    }

    $quantos = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tabela WHERE user_id = %d AND produto_id = %d", $usuario_id, $produto_id));

    if ($quantos >= CONEXAO_APP_MAX_MARCADORES) {
        return conexao_app_invalido(['pagina' => 'Este livro já tem marcadores demais. Remova alguns.']);
    }

    // INSERT IGNORE: dois toques seguidos no mesmo marcador não duplicam
    $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO $tabela (user_id, produto_id, pagina, criado_em) VALUES (%d, %d, %d, %s)",
        $usuario_id,
        $produto_id,
        $pagina,
        conexao_app_agora()
    ));

    $novo = $wpdb->get_row($consulta);

    return $novo ? new WP_REST_Response(conexao_app_marcador($novo), 201) : conexao_app_erro('indisponivel');
}

/** DELETE /livros/{id}/marcadores/{marcador} */
function conexao_app_rota_desmarca(WP_REST_Request $request)
{
    global $wpdb;

    $produto_id = conexao_app_produto_da_rota($request);

    if (is_wp_error($produto_id)) {
        return $produto_id;
    }

    // apagar o próprio marcador não depende de ainda ter acesso ao livro
    $apagados = $wpdb->delete(conexao_app_tabela('marcadores'), [
        'id' => (int) $request['marcador'],
        'user_id' => get_current_user_id(),
        'produto_id' => $produto_id,
    ], ['%d', '%d', '%d']);

    if (! $apagados) {
        return conexao_app_erro('nao_encontrado', 'Marcador não encontrado.');
    }

    return new WP_REST_Response(null, 204);
}
