<?php
/**
 * Entrada de uso único no site para o leitor de flipbook (legado). O leitor do
 * site depende do cookie de login; o navegador interno do app não tem esse
 * cookie. O app pede um link (válido por 120 s, uma vez só) e o abre: o site
 * cria uma sessão de navegador (sem "lembrar de mim") e manda para o leitor.
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_APP_ENTRADA_VALIDADE = 120;

/**
 * Só contas de leitor ganham a entrada: para quem é da equipe (edita conteúdo
 * ou gerencia a loja) o link viraria uma sessão com acesso ao painel, e um
 * token do app vazado não pode valer mais que a leitura. A equipe lê pelo site.
 */
function conexao_app_entrada_permitida(int $user_id): bool
{
    return ! user_can($user_id, 'edit_posts') && ! user_can($user_id, 'manage_woocommerce');
}

/** POST /livros/{id}/acesso-web */
function conexao_app_rota_acesso_web(WP_REST_Request $request)
{
    $produto_id = conexao_app_livro_com_acesso($request);

    if (is_wp_error($produto_id)) {
        return $produto_id;
    }

    if (! conexao_app_entrada_permitida(get_current_user_id())) {
        return conexao_app_erro('sem_acesso', 'Contas da equipe leem este livro pelo site.');
    }

    if (conexao_biblioteca_livro($produto_id)['modelo'] === '') {
        return conexao_app_erro('nao_encontrado', 'Este livro ainda está sendo preparado para leitura.');
    }

    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

    set_transient('conexao_app_entrada_'.hash('sha256', $token), [
        'user_id' => get_current_user_id(),
        'produto_id' => $produto_id,
        'expira' => time() + CONEXAO_APP_ENTRADA_VALIDADE,
    ], CONEXAO_APP_ENTRADA_VALIDADE);

    return ['url' => add_query_arg('cxa_entrada', $token, home_url('/'))];
}

/** Sem detalhe do motivo: link vencido, usado ou inválido dão no mesmo lugar. */
function conexao_app_entrada_recusada(): void
{
    $entrar = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url();
    wp_safe_redirect($entrar ?: wp_login_url(), 302, 'Conexao App');
    exit;
}

// cedo no init: antes de qualquer redirecionamento ou tela; só por GET
add_action('init', function (): void {
    if (! isset($_GET['cxa_entrada']) || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return;
    }

    nocache_headers();
    // o token não pode vazar para o destino nem para terceiros pelo Referer
    header('Referrer-Policy: no-referrer');

    $token = is_string($_GET['cxa_entrada']) ? (string) $_GET['cxa_entrada'] : '';

    if (! preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) {
        conexao_app_entrada_recusada();
    }

    $chave = 'conexao_app_entrada_'.hash('sha256', $token);
    $dados = get_transient($chave);
    // uso único: some antes de qualquer conferência
    delete_transient($chave);

    $user_id = is_array($dados) ? (int) ($dados['user_id'] ?? 0) : 0;
    $produto_id = is_array($dados) ? (int) ($dados['produto_id'] ?? 0) : 0;

    if (! $user_id || ! $produto_id || (int) ($dados['expira'] ?? 0) < time()
        || ! get_userdata($user_id) || ! conexao_app_entrada_permitida($user_id)
        || ! function_exists('conexao_biblioteca_pode_ler')
        || ! conexao_biblioteca_pode_ler($user_id, $produto_id)) {
        conexao_app_entrada_recusada();
    }

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, false);
    wp_safe_redirect(conexao_biblioteca_url_leitor($produto_id), 302, 'Conexao App');
    exit;
}, 1);
