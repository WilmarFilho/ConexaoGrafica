<?php
/**
 * Forma das respostas do app: erros no formato do contrato
 * ({codigo, mensagem}) e CORS só para este namespace. As outras APIs do site
 * (loja, DOI) continuam com o comportamento padrão do WordPress.
 */

if (! defined('ABSPATH')) {
    exit;
}

/** Mensagens padrão de cada código do contrato. */
function conexao_app_mensagens(): array
{
    return [
        'nao_autenticado' => ['Sua sessão terminou. Entre de novo.', 401],
        'sem_acesso' => ['Este livro não está na sua estante.', 403],
        'nao_encontrado' => ['Não encontramos o que você procurou.', 404],
        'limite_aparelhos' => ['Você atingiu o limite de aparelhos conectados. Desconecte um para entrar neste.', 409],
        'muitas_tentativas' => ['Muitas tentativas seguidas. Aguarde alguns minutos e tente de novo.', 429],
        'invalido' => ['Confira os dados informados.', 422],
        'indisponivel' => ['Serviço indisponível no momento. Tente de novo em instantes.', 503],
    ];
}

/**
 * Erro do contrato. $extra vai junto no corpo (aparelhos, campos).
 */
function conexao_app_erro(string $codigo, string $mensagem = '', array $extra = []): WP_Error
{
    [$padrao, $status] = conexao_app_mensagens()[$codigo] ?? ['Algo deu errado.', 500];

    return new WP_Error($codigo, $mensagem !== '' ? $mensagem : $padrao, ['status' => $status, 'extra' => $extra]);
}

/** 422 com a mensagem de cada campo. */
function conexao_app_invalido(array $campos): WP_Error
{
    return conexao_app_erro('invalido', '', ['campos' => $campos]);
}

function conexao_app_e_rota_do_app(WP_REST_Request $request): bool
{
    return str_starts_with(ltrim($request->get_route(), '/'), CONEXAO_APP_NS);
}

/**
 * Todo erro do namespace sai no formato do contrato, inclusive os que o
 * próprio WordPress gera (rota inexistente, parâmetro fora do padrão da URL).
 */
add_filter('rest_post_dispatch', function ($resposta, $servidor, WP_REST_Request $request) {
    if (! conexao_app_e_rota_do_app($request)) {
        return $resposta;
    }

    if (is_wp_error($resposta)) {
        $resposta = rest_convert_error_to_response($resposta);
    }

    if (! $resposta instanceof WP_REST_Response || ! $resposta->is_error()) {
        return $resposta;
    }

    $dados = (array) $resposta->get_data();
    $codigo = (string) ($dados['code'] ?? '');
    $mensagem = (string) ($dados['message'] ?? '');
    $extra = (array) ($dados['data']['extra'] ?? []);
    $status = $resposta->get_status();
    $mensagens = conexao_app_mensagens();

    if (! isset($mensagens[$codigo])) {
        // erro do próprio WordPress: traduz pelo status, sem detalhe interno
        $codigo = match (true) {
            $status === 401 => 'nao_autenticado',
            $status === 403 => 'sem_acesso',
            $status === 404, $status === 405 => 'nao_encontrado',
            $status === 400, $status === 422 => 'invalido',
            $status === 429 => 'muitas_tentativas',
            default => 'indisponivel',
        };
        $mensagem = $mensagens[$codigo][0];
        $status = $mensagens[$codigo][1];

        if ($codigo === 'invalido') {
            $campos = [];

            foreach ((array) ($dados['data']['params'] ?? []) as $campo => $texto) {
                $campos[sanitize_key((string) $campo)] = 'Valor inválido.';
            }

            $extra = ['campos' => $campos];
        }
    }

    $resposta->set_data(['codigo' => $codigo, 'mensagem' => $mensagem] + $extra);
    $resposta->set_status($status);

    return $resposta;
}, 99, 3);

// ---------------------------------------------------------------- CORS

/** Origens liberadas: os apps nativos (Capacitor) e o próprio site. */
function conexao_app_origens(): array
{
    $origens = ['capacitor://localhost', 'https://localhost', 'http://localhost'];
    $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);

    if ($host !== '') {
        $origens[] = 'https://'.$host;
        $origens[] = 'http://'.$host; // só vale enquanto o site não for HTTPS (staging)
    }

    return array_values(array_unique((array) apply_filters('conexao_app_origens', $origens)));
}

/**
 * Cabeçalhos CORS do app. Sem Allow-Credentials: o app não usa cookie,
 * só o token no Authorization.
 */
function conexao_app_envia_cors(): void
{
    if (headers_sent()) {
        return;
    }

    // o WordPress já mandou os dele (que ecoam qualquer origem e liberam cookie)
    foreach (['Access-Control-Allow-Origin', 'Access-Control-Allow-Credentials', 'Access-Control-Allow-Methods', 'Access-Control-Allow-Headers', 'Access-Control-Expose-Headers'] as $cabecalho) {
        header_remove($cabecalho);
    }

    header('Vary: Origin', false);

    $origem = isset($_SERVER['HTTP_ORIGIN']) ? trim((string) wp_unslash($_SERVER['HTTP_ORIGIN'])) : '';

    if ($origem === '' || ! in_array($origem, conexao_app_origens(), true)) {
        return;
    }

    header('Access-Control-Allow-Origin: '.$origem);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    // Content-Type para o corpo JSON, Range para o leitor pedir o PDF em pedaços
    header('Access-Control-Allow-Headers: Authorization, Content-Type, Range');
    header('Access-Control-Expose-Headers: X-Conexao-Marca, Content-Length, Content-Range, Accept-Ranges');
    header('Access-Control-Max-Age: 600');
}

// depois do rest_send_cors_headers do núcleo (prioridade 10), só nas rotas do app
add_filter('rest_pre_serve_request', function ($servido, $resultado, WP_REST_Request $request) {
    if (conexao_app_e_rota_do_app($request)) {
        conexao_app_envia_cors();
    }

    return $servido;
}, 11, 3);
