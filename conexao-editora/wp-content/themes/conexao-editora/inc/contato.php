<?php
/**
 * Recebe o formulário de contato e manda por e-mail para a editora.
 */

if (! defined('ABSPATH')) {
    exit;
}

/** Opções do campo "Tipo de serviço". */
function conexao_assuntos_contato(): array
{
    return apply_filters('conexao_assuntos_contato', [
        'Quero publicar meu livro',
        'Compra corporativa',
        'Dúvida sobre um pedido',
        'Imprensa',
        'Outro assunto',
    ]);
}

add_action('admin_post_nopriv_conexao_contato', 'conexao_recebe_contato');
add_action('admin_post_conexao_contato', 'conexao_recebe_contato');

function conexao_recebe_contato(): void
{
    $volta = wp_get_referer() ?: home_url('/contato/');

    if (! isset($_POST['conexao_contato_nonce']) || ! wp_verify_nonce(sanitize_key(wp_unslash($_POST['conexao_contato_nonce'])), 'conexao_contato')) {
        wp_safe_redirect(add_query_arg('contato', 'erro', $volta));
        exit;
    }

    // campo escondido: se veio preenchido, é robô
    if (! empty($_POST['site'])) {
        wp_safe_redirect(add_query_arg('contato', 'ok', $volta));
        exit;
    }

    $nome = sanitize_text_field(wp_unslash($_POST['nome'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $telefone = sanitize_text_field(wp_unslash($_POST['telefone'] ?? ''));
    $assunto = sanitize_text_field(wp_unslash($_POST['assunto'] ?? ''));
    $mensagem = sanitize_textarea_field(wp_unslash($_POST['mensagem'] ?? ''));

    if ($nome === '' || ! is_email($email)) {
        wp_safe_redirect(add_query_arg('contato', 'dados', $volta));
        exit;
    }

    $destino = get_theme_mod('conexao_email', get_option('admin_email'));
    $titulo = sprintf('[Site] %s', $assunto ?: 'Contato pelo site');

    $corpo = implode("\n", array_filter([
        'Nome: '.$nome,
        'E-mail: '.$email,
        $telefone ? 'Telefone: '.$telefone : '',
        $assunto ? 'Tipo de serviço: '.$assunto : '',
        '',
        $mensagem,
    ], static fn ($linha) => $linha !== ''));

    $enviado = wp_mail(
        $destino,
        $titulo,
        $corpo,
        [
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: '.$nome.' <'.$email.'>',
        ]
    );

    wp_safe_redirect(add_query_arg('contato', $enviado ? 'ok' : 'erro', $volta));
    exit;
}

/**
 * Remetente dos e-mails do site. O padrão do WordPress é wordpress@localhost,
 * que servidor nenhum aceita.
 */
add_filter('wp_mail_from', function (string $email): string {
    if ($email !== 'wordpress@localhost') {
        return $email;
    }

    $contato = (string) get_theme_mod('conexao_email', '');
    $dominio = $contato && str_contains($contato, '@')
        ? substr($contato, strpos($contato, '@') + 1)
        : 'conexaoeditora.com.br';

    return 'nao-responda@'.$dominio;
});

add_filter('wp_mail_from_name', fn () => get_bloginfo('name'));

/**
 * Em desenvolvimento, os e-mails vão para o Mailpit (http://localhost:8026),
 * em vez de tentarem sair pela internet. Em produção, nada muda.
 */
add_action('phpmailer_init', function ($phpmailer) {
    $host = getenv('CONEXAO_SMTP_HOST');

    if (! $host) {
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host = $host;
    $phpmailer->Port = (int) (getenv('CONEXAO_SMTP_PORT') ?: 1025);
    $phpmailer->SMTPAuth = false;
    $phpmailer->SMTPAutoTLS = false;
});
