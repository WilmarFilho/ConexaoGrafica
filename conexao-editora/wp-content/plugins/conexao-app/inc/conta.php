<?php
/**
 * Conta: login, cadastro, esqueci a senha, sair, quem sou eu e aparelhos.
 * O login é o mesmo do site (usuários do WordPress/WooCommerce).
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_APP_TENTATIVAS = 10;      // falhas de senha por e-mail+IP
const CONEXAO_APP_JANELA = 900;         // 15 minutos
const CONEXAO_APP_ENVIOS_SENHA = 5;     // pedidos de "esqueci a senha" por IP na janela
const CONEXAO_APP_CADASTROS = 10;       // tentativas de cadastro por IP por hora

// ---------------------------------------------------------------- limite de tentativas

/** Contador numa janela fixa (transient guarda o início, para não esticar a janela a cada falha). */
function conexao_app_contador(string $chave): array
{
    $dados = get_transient($chave);

    return is_array($dados) ? $dados + ['n' => 0, 'desde' => time()] : ['n' => 0, 'desde' => time()];
}

function conexao_app_conta_falha(string $chave, int $janela): void
{
    $dados = conexao_app_contador($chave);
    $dados['n']++;
    set_transient($chave, $dados, max(1, $janela - (time() - (int) $dados['desde'])));
}

function conexao_app_chave_login(string $email): string
{
    return 'conexao_app_login_'.md5(strtolower(trim($email)).'|'.conexao_app_ip());
}

// ---------------------------------------------------------------- usuário

/** Nome para mostrar: nome e sobrenome do cadastro; senão o nome público. */
function conexao_app_nome_usuario(WP_User $usuario): string
{
    $nome = trim($usuario->first_name.' '.$usuario->last_name);

    return $nome !== '' ? $nome : (string) $usuario->display_name;
}

function conexao_app_iniciais(string $nome): string
{
    $partes = preg_split('/\s+/u', trim($nome)) ?: [];
    $partes = array_values(array_filter($partes, static fn ($p) => $p !== ''));

    if (! $partes) {
        return '';
    }

    $primeira = mb_substr($partes[0], 0, 1);
    $ultima = count($partes) > 1 ? mb_substr($partes[count($partes) - 1], 0, 1) : '';

    return mb_strtoupper($primeira.$ultima);
}

function conexao_app_usuario(WP_User $usuario): array
{
    $nome = conexao_app_nome_usuario($usuario);

    return [
        'id' => (int) $usuario->ID,
        'nome' => $nome,
        'email' => (string) $usuario->user_email,
        'iniciais' => conexao_app_iniciais($nome),
        'email_verificado' => conexao_app_email_verificado($usuario),
    ];
}

/** E-mail parcial da marca d'água: "an***@gmail.com". */
function conexao_app_email_parcial(string $email): string
{
    $partes = explode('@', $email, 2);

    if (count($partes) !== 2) {
        return '';
    }

    $local = $partes[0];
    $visivel = mb_strlen($local) > 2 ? mb_substr($local, 0, 2) : mb_substr($local, 0, 1);

    return $visivel.'***@'.$partes[1];
}

function conexao_app_marca(WP_User $usuario): string
{
    return trim(conexao_app_nome_usuario($usuario).' · '.conexao_app_email_parcial((string) $usuario->user_email), ' ·');
}

// ---------------------------------------------------------------- aparelho no corpo do login

/** Lê e valida {aparelho: {nome, plataforma, id_local}}. */
function conexao_app_aparelho_do_corpo(WP_REST_Request $request, array &$campos): array
{
    $aparelho = $request->get_param('aparelho');
    $aparelho = is_array($aparelho) ? $aparelho : [];
    $plataforma = is_string($aparelho['plataforma'] ?? null) ? strtolower(trim($aparelho['plataforma'])) : '';
    $id_local = is_string($aparelho['id_local'] ?? null) ? trim($aparelho['id_local']) : '';

    if (! in_array($plataforma, CONEXAO_APP_PLATAFORMAS, true)) {
        $campos['aparelho.plataforma'] = 'Informe a plataforma: ios, android ou web.';
    }

    if ($id_local !== '' && ! preg_match('/^[A-Za-z0-9._:-]{1,64}$/', $id_local)) {
        $campos['aparelho.id_local'] = 'Identificador do aparelho inválido.';
    }

    return [
        'nome' => is_string($aparelho['nome'] ?? null) ? $aparelho['nome'] : '',
        'plataforma' => $plataforma,
        'id_local' => $id_local,
    ];
}

/**
 * Entra o usuário já autenticado: libera a vaga pedida (substituir), reaproveita
 * a vaga do mesmo aparelho (id_local) e respeita o limite.
 */
function conexao_app_entra(WP_User $usuario, array $aparelho, int $substituir, int $status = 200)
{
    if ($substituir > 0) {
        conexao_app_revoga_aparelho($usuario->ID, $substituir);
    }

    if ($aparelho['id_local'] !== '') {
        foreach (conexao_app_aparelhos_ativos($usuario->ID) as $linha) {
            if (hash_equals((string) $linha->id_local, $aparelho['id_local'])) {
                conexao_app_revoga_aparelho($usuario->ID, (int) $linha->id);
            }
        }
    }

    $ativos = conexao_app_lista_aparelhos($usuario->ID);
    $limite = conexao_app_limite_aparelhos();

    if (count($ativos) >= $limite) {
        return conexao_app_erro(
            'limite_aparelhos',
            sprintf('Sua conta já está conectada em %d aparelhos. Desconecte um para entrar neste.', count($ativos)),
            ['aparelhos' => $ativos]
        );
    }

    conexao_app_limpa_revogados();

    try {
        $novo = conexao_app_cria_aparelho($usuario->ID, $aparelho['nome'], $aparelho['plataforma'], $aparelho['id_local'], conexao_app_ip());
    } catch (Throwable $e) {
        return conexao_app_erro('indisponivel');
    }

    return new WP_REST_Response([
        'token' => $novo['token'],
        'usuario' => conexao_app_usuario($usuario),
        'aparelho' => $novo['aparelho'],
    ], $status);
}

// ---------------------------------------------------------------- rotas

/** POST /login */
function conexao_app_rota_login(WP_REST_Request $request)
{
    $email = is_string($request->get_param('email')) ? trim($request->get_param('email')) : '';
    $senha = is_string($request->get_param('senha')) ? (string) $request->get_param('senha') : '';
    $substituir = absint($request->get_param('substituir'));
    $campos = [];

    if ($email === '' || strlen($email) > 200) {
        $campos['email'] = 'Informe seu e-mail.';
    }

    if ($senha === '' || strlen($senha) > 4096) {
        $campos['senha'] = 'Informe sua senha.';
    }

    $aparelho = conexao_app_aparelho_do_corpo($request, $campos);

    if ($campos) {
        return conexao_app_invalido($campos);
    }

    $chave = conexao_app_chave_login($email);

    if (conexao_app_contador($chave)['n'] >= CONEXAO_APP_TENTATIVAS) {
        return conexao_app_erro('muitas_tentativas');
    }

    // aceita e-mail ou nome de usuário, como o login do site
    $usuario = wp_authenticate($email, $senha);

    if (! $usuario instanceof WP_User) {
        conexao_app_conta_falha($chave, CONEXAO_APP_JANELA);

        return conexao_app_erro('nao_autenticado', 'E-mail ou senha incorretos.');
    }

    delete_transient($chave);

    return conexao_app_entra($usuario, $aparelho, $substituir);
}

/** POST /cadastro */
function conexao_app_rota_cadastro(WP_REST_Request $request)
{
    if (! function_exists('wc_create_new_customer')) {
        return conexao_app_erro('indisponivel');
    }

    if (get_option('woocommerce_enable_myaccount_registration') !== 'yes') {
        return conexao_app_erro('sem_acesso', 'O cadastro de novas contas está fechado no momento.');
    }

    $nome = is_string($request->get_param('nome')) ? trim(sanitize_text_field($request->get_param('nome'))) : '';
    $email = is_string($request->get_param('email')) ? sanitize_email(trim($request->get_param('email'))) : '';
    $senha = is_string($request->get_param('senha')) ? (string) $request->get_param('senha') : '';
    $campos = [];

    if ($nome === '' || mb_strlen($nome) > 120) {
        $campos['nome'] = 'Informe seu nome.';
    }

    if (! is_email($email)) {
        $campos['email'] = 'Informe um e-mail válido.';
    }

    if (mb_strlen($senha) < 8 || strlen($senha) > 4096) {
        $campos['senha'] = 'A senha precisa ter pelo menos 8 caracteres.';
    }

    $aparelho = conexao_app_aparelho_do_corpo($request, $campos);

    if ($campos) {
        return conexao_app_invalido($campos);
    }

    $chave = 'conexao_app_cadastro_'.md5(conexao_app_ip());

    if (conexao_app_contador($chave)['n'] >= CONEXAO_APP_CADASTROS) {
        return conexao_app_erro('muitas_tentativas');
    }

    // conta toda tentativa: "e-mail já existe" não pode virar consulta em massa
    conexao_app_conta_falha($chave, HOUR_IN_SECONDS);

    if (email_exists($email)) {
        return conexao_app_invalido(['email' => 'Já existe uma conta com este e-mail. Entre com sua senha ou use "Esqueci a senha".']);
    }

    $partes = preg_split('/\s+/u', $nome, 2);
    $dados = ['first_name' => $partes[0] ?? '', 'last_name' => $partes[1] ?? '', 'display_name' => $nome];
    // o nome de usuário é gerado como no cadastro do site (a pessoa só vê o e-mail)
    $login = function_exists('wc_create_new_customer_username') ? wc_create_new_customer_username($email, $dados) : '';
    $usuario_id = wc_create_new_customer($email, $login, $senha, $dados);

    if (is_wp_error($usuario_id)) {
        $codigo = $usuario_id->get_error_code();

        if (str_contains($codigo, 'email')) {
            return conexao_app_invalido(['email' => wp_strip_all_tags($usuario_id->get_error_message())]);
        }

        if (str_contains($codigo, 'password')) {
            return conexao_app_invalido(['senha' => wp_strip_all_tags($usuario_id->get_error_message())]);
        }

        return conexao_app_erro('indisponivel', 'Não foi possível criar a conta agora. Tente de novo em instantes.');
    }

    $usuario = get_userdata((int) $usuario_id);

    return $usuario ? conexao_app_entra($usuario, $aparelho, 0, 201) : conexao_app_erro('indisponivel');
}

/** POST /senha: sempre 204, exista ou não a conta. */
function conexao_app_rota_senha(WP_REST_Request $request)
{
    $email = is_string($request->get_param('email')) ? trim($request->get_param('email')) : '';

    if ($email === '' || strlen($email) > 200) {
        return conexao_app_invalido(['email' => 'Informe seu e-mail.']);
    }

    $chave = 'conexao_app_senha_'.md5(conexao_app_ip());

    if (conexao_app_contador($chave)['n'] >= CONEXAO_APP_ENVIOS_SENHA) {
        return conexao_app_erro('muitas_tentativas');
    }

    conexao_app_conta_falha($chave, CONEXAO_APP_JANELA);

    $login = is_email($email) ? sanitize_email($email) : sanitize_user($email);

    // o mesmo e-mail de redefinição do site; o resultado não é revelado
    if ($login !== '' && (get_user_by('email', $login) || get_user_by('login', $login))) {
        retrieve_password($login);
    }

    return new WP_REST_Response(null, 204);
}

/** POST /sair */
function conexao_app_rota_sair(WP_REST_Request $request)
{
    $aparelho = conexao_app_aparelho_atual();
    conexao_app_revoga_aparelho(get_current_user_id(), (int) $aparelho->id);

    return new WP_REST_Response(null, 204);
}

/** GET /eu */
function conexao_app_rota_eu(WP_REST_Request $request)
{
    return [
        'usuario' => conexao_app_usuario(wp_get_current_user()),
        'aparelho_atual_id' => (int) conexao_app_aparelho_atual()->id,
    ];
}

/** GET /aparelhos */
function conexao_app_rota_aparelhos(WP_REST_Request $request)
{
    return [
        'limite' => conexao_app_limite_aparelhos(),
        'aparelhos' => conexao_app_lista_aparelhos(get_current_user_id(), (int) conexao_app_aparelho_atual()->id),
    ];
}

/** DELETE /aparelhos/{id}: só os do próprio usuário (o de outra pessoa dá 404). */
function conexao_app_rota_desconecta(WP_REST_Request $request)
{
    if (! conexao_app_revoga_aparelho(get_current_user_id(), (int) $request['id'])) {
        return conexao_app_erro('nao_encontrado', 'Aparelho não encontrado.');
    }

    return new WP_REST_Response(null, 204);
}
