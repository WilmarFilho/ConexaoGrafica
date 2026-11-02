<?php
/**
 * Remetente dos e-mails do site (WooCommerce e WordPress): o endereço do
 * domínio da editora, com "Responder para" numa caixa que existe.
 *
 * O domínio não tem caixa de entrada (sem MX): o e-mail sai como
 * contato@conexaoeditora.com.br, autenticado pelo SPF e pelo DKIM do domínio,
 * e a resposta do cliente vai para o comercial. O remetente do envelope
 * (Return-Path) acompanha o "De:", senão o Gmail confere o SPF do nome do
 * servidor e recusa a mensagem.
 */

if (! defined('ABSPATH')) {
    exit;
}

function conexao_email_remetente(): string
{
    return defined('CONEXAO_EMAIL_REMETENTE') ? (string) CONEXAO_EMAIL_REMETENTE : 'contato@conexaoeditora.com.br';
}

function conexao_email_resposta(): string
{
    return defined('CONEXAO_EMAIL_RESPOSTA') ? (string) CONEXAO_EMAIL_RESPOSTA : 'comercial@conexaopro.com.br';
}

// WooCommerce: o campo "Remetente" das configurações deixa de valer
add_filter('woocommerce_email_from_address', 'conexao_email_remetente', 99);
add_filter('woocommerce_email_from_name', fn () => 'Conexão Editora', 99);

// WordPress (senha, cadastro, avisos): o padrão seria wordpress@…
add_filter('wp_mail_from', function (string $email): string {
    return str_starts_with($email, 'wordpress@') ? conexao_email_remetente() : $email;
});

add_filter('wp_mail_from_name', function (string $nome): string {
    return $nome === 'WordPress' ? 'Conexão Editora' : $nome;
});

// "Responder para" o comercial, quando a mensagem ainda não tem um (o
// formulário de contato e o aviso de pedido novo já trazem o do cliente)
add_filter('wp_mail', function (array $args): array {
    $cabecalhos = $args['headers'] ?? [];
    $lista = is_array($cabecalhos) ? $cabecalhos : preg_split('/\r?\n/', (string) $cabecalhos);

    foreach ($lista as $linha) {
        if (stripos(trim((string) $linha), 'reply-to:') === 0) {
            return $args;
        }
    }

    $lista[] = 'Reply-To: Conexão Editora <'.conexao_email_resposta().'>';
    $args['headers'] = array_values(array_filter($lista, static fn ($l) => trim((string) $l) !== ''));

    return $args;
}, 99);

// envelope = remetente do domínio (o SPF é conferido por ele); fora o Mailpit do ambiente local
add_action('phpmailer_init', function ($phpmailer) {
    if (getenv('CONEXAO_SMTP_HOST')) {
        return;
    }

    if (str_ends_with(strtolower((string) $phpmailer->From), '@conexaoeditora.com.br')) {
        $phpmailer->Sender = $phpmailer->From;
    }
}, 20);
