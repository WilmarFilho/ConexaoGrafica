<?php
/**
 * Tokens de aparelho. O token (cxa_ + 32 bytes em base64url) só existe na
 * resposta do login; o banco guarda o SHA-256. A busca é pelo hash, então não
 * há comparação de segredo byte a byte que vaze tempo.
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_APP_PLATAFORMAS = ['ios', 'android', 'web'];

function conexao_app_limite_aparelhos(): int
{
    return max(1, (int) get_option('conexao_app_limite_aparelhos', 3));
}

function conexao_app_hash_token(string $token): string
{
    return hash('sha256', $token);
}

/** IP de quem chama. Só REMOTE_ADDR: X-Forwarded-For é forjável. */
function conexao_app_ip(): string
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) wp_unslash($_SERVER['REMOTE_ADDR']) : '';
    $ip = (string) apply_filters('conexao_app_ip', $ip);

    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

/**
 * Cria o aparelho e devolve o token (a única vez em que ele aparece).
 *
 * @return array{token: string, aparelho: array}
 */
function conexao_app_cria_aparelho(int $user_id, string $nome, string $plataforma, string $id_local = '', string $ip = ''): array
{
    global $wpdb;

    $token = 'cxa_'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $agora = conexao_app_agora();
    $plataforma = in_array($plataforma, CONEXAO_APP_PLATAFORMAS, true) ? $plataforma : 'web';
    $nome = conexao_app_limpa_nome_aparelho($nome, $plataforma);

    $wpdb->insert(conexao_app_tabela('aparelhos'), [
        'user_id' => $user_id,
        'token_hash' => conexao_app_hash_token($token),
        'nome' => $nome,
        'plataforma' => $plataforma,
        'id_local' => $id_local,
        'criado_em' => $agora,
        'usado_em' => $agora,
        'ip' => $ip,
    ]);

    $id = (int) $wpdb->insert_id;

    if (! $id) {
        throw new RuntimeException('Não foi possível gravar o aparelho.');
    }

    return [
        'token' => $token,
        'aparelho' => ['id' => $id, 'nome' => $nome, 'plataforma' => $plataforma, 'usado_em' => conexao_app_iso($agora), 'atual' => true],
    ];
}

function conexao_app_limpa_nome_aparelho(string $nome, string $plataforma): string
{
    $nome = trim(sanitize_text_field($nome));
    $nome = function_exists('mb_substr') ? mb_substr($nome, 0, 80) : substr($nome, 0, 80);

    if ($nome === '') {
        $nome = ['ios' => 'iPhone ou iPad', 'android' => 'Android', 'web' => 'Navegador'][$plataforma] ?? 'Aparelho';
    }

    return $nome;
}

/** Aparelhos ativos do usuário, mais recentes primeiro. */
function conexao_app_aparelhos_ativos(int $user_id): array
{
    global $wpdb;

    return (array) $wpdb->get_results($wpdb->prepare(
        'SELECT id, nome, plataforma, id_local, usado_em FROM '.conexao_app_tabela('aparelhos').'
         WHERE user_id = %d AND revogado_em IS NULL ORDER BY usado_em DESC',
        $user_id
    ));
}

/** Aparelhos no formato do contrato. */
function conexao_app_lista_aparelhos(int $user_id, int $atual_id = 0): array
{
    return array_map(static fn ($linha) => [
        'id' => (int) $linha->id,
        'nome' => (string) $linha->nome,
        'plataforma' => (string) $linha->plataforma,
        'usado_em' => conexao_app_iso((string) $linha->usado_em),
        'atual' => (int) $linha->id === $atual_id,
    ], conexao_app_aparelhos_ativos($user_id));
}

/** Revoga um aparelho do próprio usuário. Devolve false se não era dele ou já estava revogado. */
function conexao_app_revoga_aparelho(int $user_id, int $aparelho_id): bool
{
    global $wpdb;

    return (bool) $wpdb->query($wpdb->prepare(
        'UPDATE '.conexao_app_tabela('aparelhos').' SET revogado_em = %s
         WHERE id = %d AND user_id = %d AND revogado_em IS NULL',
        conexao_app_agora(),
        $aparelho_id,
        $user_id
    ));
}

function conexao_app_revoga_todos(int $user_id): void
{
    global $wpdb;

    $wpdb->query($wpdb->prepare(
        'UPDATE '.conexao_app_tabela('aparelhos').' SET revogado_em = %s WHERE user_id = %d AND revogado_em IS NULL',
        conexao_app_agora(),
        $user_id
    ));
}

/** Registros revogados há mais de 180 dias não servem nem para histórico. */
function conexao_app_limpa_revogados(): void
{
    global $wpdb;

    $wpdb->query($wpdb->prepare(
        'DELETE FROM '.conexao_app_tabela('aparelhos').' WHERE revogado_em IS NOT NULL AND revogado_em < %s',
        gmdate('Y-m-d H:i:s', time() - 180 * DAY_IN_SECONDS)
    ));
}

// ---------------------------------------------------------------- autenticação

/** O aparelho da requisição atual (preenchido por conexao_app_autentica). */
function conexao_app_aparelho_atual(?object $definir = null): ?object
{
    static $atual = null;

    if ($definir !== null) {
        $atual = $definir;
    }

    return $atual;
}

/** Token do cabeçalho Authorization: Bearer cxa_... (vazio se ausente ou fora do formato). */
function conexao_app_token_da_requisicao(WP_REST_Request $request): string
{
    $cabecalho = (string) $request->get_header('authorization');

    if ($cabecalho === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $cabecalho = (string) wp_unslash($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    if (! preg_match('/^Bearer\s+(cxa_[A-Za-z0-9_-]{43})$/', trim($cabecalho), $m)) {
        return '';
    }

    return $m[1];
}

/**
 * permission_callback de todas as rotas autenticadas: acha o aparelho pelo
 * hash, confere o usuário e o põe como usuário corrente desta requisição.
 */
function conexao_app_autentica(WP_REST_Request $request)
{
    global $wpdb;

    $token = conexao_app_token_da_requisicao($request);

    if ($token === '') {
        return conexao_app_erro('nao_autenticado');
    }

    $aparelho = $wpdb->get_row($wpdb->prepare(
        'SELECT id, user_id, nome, plataforma, usado_em FROM '.conexao_app_tabela('aparelhos').'
         WHERE token_hash = %s AND revogado_em IS NULL',
        conexao_app_hash_token($token)
    ));

    $usuario = $aparelho ? get_userdata((int) $aparelho->user_id) : false;

    if (! $aparelho || ! $usuario) {
        return conexao_app_erro('nao_autenticado');
    }

    wp_set_current_user($usuario->ID);
    conexao_app_aparelho_atual($aparelho);

    // "usado em" com precisão de minutos basta; evita uma escrita por chamada
    if (strtotime($aparelho->usado_em.' UTC') < time() - 60) {
        $wpdb->update(
            conexao_app_tabela('aparelhos'),
            ['usado_em' => conexao_app_agora(), 'ip' => conexao_app_ip()],
            ['id' => (int) $aparelho->id]
        );
    }

    return true;
}

// ---------------------------------------------------------------- senha trocada, conta removida

// senha nova derruba todos os aparelhos (quem trocou entra de novo)
add_action('after_password_reset', 'conexao_app_senha_redefinida');
add_action('profile_update', 'conexao_app_perfil_atualizado', 10, 2);
// wp_set_password() direto (painel, WP-CLI) não passa por profile_update
add_action('wp_set_password', 'conexao_app_senha_definida', 10, 2);
// conta removida: some tudo o que o app guardou dela
add_action('deleted_user', 'conexao_app_conta_removida');

function conexao_app_senha_redefinida($usuario): void
{
    if ($usuario instanceof WP_User) {
        conexao_app_revoga_todos($usuario->ID);
    }
}

function conexao_app_perfil_atualizado($user_id, $antes = null): void
{
    $agora = get_userdata((int) $user_id);

    if ($antes instanceof WP_User && $agora && ! hash_equals((string) $antes->user_pass, (string) $agora->user_pass)) {
        conexao_app_revoga_todos((int) $user_id);
    }
}

function conexao_app_senha_definida($senha, $user_id = 0): void
{
    if ((int) $user_id) {
        conexao_app_revoga_todos((int) $user_id);
    }
}

function conexao_app_conta_removida($user_id): void
{
    global $wpdb;

    foreach (['aparelhos', 'leitura', 'marcadores'] as $tabela) {
        $wpdb->delete(conexao_app_tabela($tabela), ['user_id' => (int) $user_id]);
    }

    conexao_app_limpa_cache_pedidos((int) $user_id);
}
