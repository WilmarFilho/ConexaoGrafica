<?php
/**
 * Confirmação do e-mail da conta. O Hub encontra pedidos de outros canais
 * pelo e-mail, e o WordPress não confirma o e-mail no cadastro: sem esta
 * prova, quem criasse conta com o e-mail de outra pessoa veria os pedidos
 * dela. Só depois de confirmar (código por e-mail, ou redefinição de senha
 * pelo link do e-mail) o app consulta o Hub.
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_APP_META_VERIFICADO = 'conexao_app_email_verificado'; // o e-mail confirmado, em minúsculas
const CONEXAO_APP_META_CODIGO = '_conexao_app_email_codigo';        // hash, e-mail, expira, tentativas
const CONEXAO_APP_META_ENVIOS = '_conexao_app_email_envios';        // horários dos envios na última hora
const CONEXAO_APP_CODIGO_VALIDADE = 900;  // 15 minutos
const CONEXAO_APP_CODIGO_TENTATIVAS = 5;
const CONEXAO_APP_ENVIOS_HORA = 5;
const CONEXAO_APP_ENVIO_INTERVALO = 60;

/** Verificado só enquanto o e-mail confirmado for o e-mail atual da conta. */
function conexao_app_email_verificado(WP_User $usuario): bool
{
    $confirmado = (string) get_user_meta($usuario->ID, CONEXAO_APP_META_VERIFICADO, true);

    return $confirmado !== '' && hash_equals($confirmado, strtolower(trim((string) $usuario->user_email)));
}

function conexao_app_marca_email_verificado(WP_User $usuario): void
{
    update_user_meta($usuario->ID, CONEXAO_APP_META_VERIFICADO, strtolower(trim((string) $usuario->user_email)));
    conexao_app_limpa_cache_pedidos($usuario->ID);
}

/** Hash do código com a chave do site: o banco sozinho não revela o código. */
function conexao_app_hash_codigo(int $user_id, string $codigo): string
{
    return hash_hmac('sha256', $user_id.'|'.$codigo, wp_salt('auth'));
}

// redefinir a senha pelo link do e-mail prova o controle da caixa de entrada
add_action('after_password_reset', 'conexao_app_verifica_por_redefinicao');

function conexao_app_verifica_por_redefinicao($usuario): void
{
    if ($usuario instanceof WP_User) {
        conexao_app_marca_email_verificado($usuario);
    }
}

// ---------------------------------------------------------------- rotas

/** POST /email/codigo: manda um código de 6 dígitos para o e-mail da conta. */
function conexao_app_rota_email_codigo(WP_REST_Request $request)
{
    $usuario = wp_get_current_user();
    $agora = time();

    if (conexao_app_email_verificado($usuario)) {
        return new WP_REST_Response(null, 204);
    }

    $envios = array_values(array_filter(
        array_map('intval', (array) get_user_meta($usuario->ID, CONEXAO_APP_META_ENVIOS, true)),
        static fn ($t) => $t > $agora - HOUR_IN_SECONDS
    ));

    if (($envios && max($envios) > $agora - CONEXAO_APP_ENVIO_INTERVALO) || count($envios) >= CONEXAO_APP_ENVIOS_HORA) {
        return conexao_app_erro('muitas_tentativas', 'Aguarde um pouco antes de pedir outro código.');
    }

    $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $email = (string) $usuario->user_email;

    $envios[] = $agora;
    update_user_meta($usuario->ID, CONEXAO_APP_META_ENVIOS, $envios);
    // código novo substitui o anterior (e zera as tentativas)
    update_user_meta($usuario->ID, CONEXAO_APP_META_CODIGO, [
        'hash' => conexao_app_hash_codigo($usuario->ID, $codigo),
        'email' => strtolower(trim($email)),
        'expira' => $agora + CONEXAO_APP_CODIGO_VALIDADE,
        'tentativas' => 0,
    ]);

    $corpo = sprintf(
        "Olá, %s.\n\nSeu código de confirmação é: %s\n\nDigite este código no app da Conexão Editora. Ele vale por 15 minutos.\n\nSe você não pediu este código, ignore este e-mail.\n\nConexão Editora",
        conexao_app_nome_usuario($usuario),
        $codigo
    );

    if (! wp_mail($email, 'Seu código de confirmação · Conexão Editora', $corpo)) {
        delete_user_meta($usuario->ID, CONEXAO_APP_META_CODIGO);

        return conexao_app_erro('indisponivel', 'Não conseguimos enviar o e-mail agora. Tente de novo em instantes.');
    }

    return new WP_REST_Response(null, 204);
}

/** POST /email/confirmar {codigo} */
function conexao_app_rota_email_confirmar(WP_REST_Request $request)
{
    $usuario = wp_get_current_user();
    $digitado = is_scalar($request->get_param('codigo')) ? preg_replace('/\D/', '', (string) $request->get_param('codigo')) : '';
    $salvo = get_user_meta($usuario->ID, CONEXAO_APP_META_CODIGO, true);

    if (! is_array($salvo) || empty($salvo['hash'])) {
        return conexao_app_erro('invalido', 'Código incorreto ou expirado.', ['campos' => ['codigo' => 'Código incorreto ou expirado.']]);
    }

    $vale = (int) ($salvo['expira'] ?? 0) > time()
        && (int) ($salvo['tentativas'] ?? 0) < CONEXAO_APP_CODIGO_TENTATIVAS
        // o código vale para o e-mail a que foi mandado; trocou o e-mail, perdeu
        && hash_equals((string) ($salvo['email'] ?? ''), strtolower(trim((string) $usuario->user_email)));

    if (! $vale) {
        delete_user_meta($usuario->ID, CONEXAO_APP_META_CODIGO);

        return conexao_app_erro('invalido', 'Código incorreto ou expirado.', ['campos' => ['codigo' => 'Código incorreto ou expirado.']]);
    }

    if (strlen($digitado) !== 6 || ! hash_equals((string) $salvo['hash'], conexao_app_hash_codigo($usuario->ID, $digitado))) {
        $salvo['tentativas'] = (int) ($salvo['tentativas'] ?? 0) + 1;

        // quinta tentativa errada: o código morre, é preciso pedir outro
        if ($salvo['tentativas'] >= CONEXAO_APP_CODIGO_TENTATIVAS) {
            delete_user_meta($usuario->ID, CONEXAO_APP_META_CODIGO);
        } else {
            update_user_meta($usuario->ID, CONEXAO_APP_META_CODIGO, $salvo);
        }

        return conexao_app_erro('invalido', 'Código incorreto ou expirado.', ['campos' => ['codigo' => 'Código incorreto ou expirado.']]);
    }

    delete_user_meta($usuario->ID, CONEXAO_APP_META_CODIGO);
    conexao_app_marca_email_verificado($usuario);

    return new WP_REST_Response(null, 204);
}
