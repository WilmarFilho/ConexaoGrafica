<?php
/**
 * Exclusão da conta pelo próprio leitor (exigência da App Store e do Google Play
 * para apps que permitem criar conta). Pede a senha de novo; contas da equipe
 * não se excluem por aqui. Os pedidos continuam na loja (obrigação fiscal), sem
 * ligação com a conta; o resto do app some pelo hook deleted_user.
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_APP_EXCLUSAO_TENTATIVAS = 5;      // por usuário a cada 15 minutos

/** POST /conta/excluir {senha} */
function conexao_app_rota_exclui_conta(WP_REST_Request $request)
{
    $usuario = wp_get_current_user();

    if (! $usuario->exists()) {
        return conexao_app_erro('nao_autenticado');
    }

    if (user_can($usuario, 'edit_posts') || user_can($usuario, 'manage_woocommerce')) {
        return conexao_app_erro('sem_acesso', 'Contas da equipe não podem ser excluídas pelo app.');
    }

    $chave = 'conexao_app_exclusao_'.$usuario->ID;
    $tentativas = (int) get_transient($chave);

    if ($tentativas >= CONEXAO_APP_EXCLUSAO_TENTATIVAS) {
        return conexao_app_erro('muitas_tentativas');
    }

    $senha = (string) $request->get_param('senha');

    if ($senha === '') {
        return conexao_app_invalido(['senha' => 'Digite sua senha para confirmar.']);
    }

    if (! wp_check_password($senha, $usuario->user_pass, $usuario->ID)) {
        set_transient($chave, $tentativas + 1, 15 * MINUTE_IN_SECONDS);

        return conexao_app_erro('invalido', 'Senha incorreta.', ['campos' => ['senha' => 'Senha incorreta.']]);
    }

    delete_transient($chave);

    // acessos da biblioteca (cortesias, migrações) não sobrevivem à conta
    global $wpdb;
    $acessos = $wpdb->prefix.'conexao_biblioteca_acessos';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $acessos)) === $acessos) {
        $wpdb->delete($acessos, ['user_id' => (int) $usuario->ID]);
    }

    require_once ABSPATH.'wp-admin/includes/user.php';

    if (! wp_delete_user($usuario->ID)) {
        return conexao_app_erro('indisponivel', 'Não foi possível excluir a conta agora. Tente de novo em instantes.');
    }

    return new WP_REST_Response(null, 204);
}
