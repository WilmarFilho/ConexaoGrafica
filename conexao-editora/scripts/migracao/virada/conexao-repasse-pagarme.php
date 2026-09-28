<?php
/**
 * Plugin Name: Conexão — repasse dos avisos da Pagar.me
 * Description: Enquanto a Pagar.me avisar pagamentos no endereço da Pubcon, cada aviso é copiado para a loja nova (conexaoeditora.com.br). Pode ser removido quando o webhook for trocado no painel da Pagar.me.
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('woocommerce_api_pagarme-webhook', function (): void {
    $corpo = (string) file_get_contents('php://input');

    if ($corpo === '') {
        return;
    }

    // a assinatura da Pagar.me vale sobre o corpo: segue junto, sem alteração
    $assinatura = (string) ($_SERVER['HTTP_X_WEBHOOK_ASYMMETRIC_SIGNATURE'] ?? '');

    if ($assinatura === '') {
        return;
    }

    $cabecalhos = [
        'Content-Type' => $_SERVER['CONTENT_TYPE'] ?? 'application/json',
        'X-Webhook-Asymmetric-Signature' => $assinatura,
    ];

    // sem esperar resposta: a loja antiga segue tratando o aviso normalmente
    wp_remote_post('https://conexaoeditora.com.br/wc-api/pagarme-webhook/', [
        'body' => $corpo,
        'headers' => $cabecalhos,
        'timeout' => 3,
        'blocking' => false,
    ]);
}, 1);
