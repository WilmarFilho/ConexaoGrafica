<?php
/**
 * Regras do cadastro de cliente: nome, confirmação de senha e aceite dos termos.
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * O aviso de privacidade padrão do WooCommerce sai: o aceite dos termos já
 * está no formulário, como no layout.
 */
add_action('wp', function () {
    remove_action('woocommerce_register_form', 'wc_registration_privacy_policy_text', 20);
});

add_filter('woocommerce_process_registration_errors', function ($erros) {
    $nome = isset($_POST['conexao_nome']) ? sanitize_text_field(wp_unslash($_POST['conexao_nome'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    $senha = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    $senha2 = isset($_POST['conexao_password2']) ? (string) wp_unslash($_POST['conexao_password2']) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    $termos = ! empty($_POST['conexao_termos']); // phpcs:ignore WordPress.Security.NonceVerification

    if ($nome === '') {
        $erros->add('conexao_nome', 'Informe seu nome.');
    }

    if ('no' === get_option('woocommerce_registration_generate_password') && $senha !== $senha2) {
        $erros->add('conexao_password2', 'As senhas não são iguais.');
    }

    if (! $termos) {
        $erros->add('conexao_termos', 'É preciso aceitar os Termos de Uso e a Política de Privacidade.');
    }

    return $erros;
});

/** Guarda o nome informado no cadastro e a data do aceite. */
add_action('woocommerce_created_customer', function (int $cliente_id) {
    $nome = isset($_POST['conexao_nome']) ? sanitize_text_field(wp_unslash($_POST['conexao_nome'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification

    if ($nome === '') {
        return;
    }

    $partes = preg_split('/\s+/', trim($nome), 2);

    wp_update_user([
        'ID' => $cliente_id,
        'first_name' => $partes[0],
        'last_name' => $partes[1] ?? '',
        'display_name' => $nome,
    ]);

    update_user_meta($cliente_id, 'billing_first_name', $partes[0]);
    update_user_meta($cliente_id, 'billing_last_name', $partes[1] ?? '');

    if (! empty($_POST['conexao_termos'])) { // phpcs:ignore WordPress.Security.NonceVerification
        update_user_meta($cliente_id, 'conexao_aceite_termos', current_time('mysql'));
    }
});
