<?php
/**
 * Formulário do "Publique conosco": recebe os dados do autor e o arquivo do
 * original, e manda tudo por e-mail para a editora.
 */

if (! defined('ABSPATH')) {
    exit;
}

/** Gêneros oferecidos no formulário. */
function conexao_generos_obra(): array
{
    return apply_filters('conexao_generos_obra', [
        'Biografia',
        'Crônica',
        'Direito',
        'Educação',
        'História',
        'Literatura',
        'Medicina e saúde',
        'Religião',
        'Técnico ou acadêmico',
        'Outro gênero',
    ]);
}

/** Extensões e limite aceitos no envio do original. */
function conexao_arquivo_manuscrito(): array
{
    return apply_filters('conexao_arquivo_manuscrito', [
        'extensoes' => ['pdf', 'doc', 'docx'],
        'limite' => 25 * MB_IN_BYTES,
    ]);
}

add_action('admin_post_nopriv_conexao_manuscrito', 'conexao_recebe_manuscrito');
add_action('admin_post_conexao_manuscrito', 'conexao_recebe_manuscrito');

function conexao_recebe_manuscrito(): void
{
    $volta = wp_get_referer() ?: home_url('/publique-conosco/');

    $responde = static function (string $estado) use ($volta): void {
        wp_safe_redirect(add_query_arg('manuscrito', $estado, $volta).'#envie-seu-manuscrito');
        exit;
    };

    if (! isset($_POST['conexao_manuscrito_nonce']) || ! wp_verify_nonce(sanitize_key(wp_unslash($_POST['conexao_manuscrito_nonce'])), 'conexao_manuscrito')) {
        $responde('erro');
    }

    // campo escondido: se veio preenchido, é robô
    if (! empty($_POST['site'])) {
        $responde('ok');
    }

    $nome = sanitize_text_field(wp_unslash($_POST['nome'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $telefone = sanitize_text_field(wp_unslash($_POST['telefone'] ?? ''));
    $genero = sanitize_text_field(wp_unslash($_POST['genero'] ?? ''));
    $obra = sanitize_text_field(wp_unslash($_POST['obra'] ?? ''));
    $mensagem = sanitize_textarea_field(wp_unslash($_POST['mensagem'] ?? ''));

    if ($nome === '' || ! is_email($email)) {
        $responde('dados');
    }

    $anexos = [];
    $regras = conexao_arquivo_manuscrito();
    $enviado_arquivo = $_FILES['arquivo'] ?? null;

    if ($enviado_arquivo && (int) ($enviado_arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ((int) $enviado_arquivo['error'] !== UPLOAD_ERR_OK) {
            $responde('arquivo');
        }

        if ((int) $enviado_arquivo['size'] > $regras['limite']) {
            $responde('tamanho');
        }

        $extensao = strtolower(pathinfo((string) $enviado_arquivo['name'], PATHINFO_EXTENSION));

        if (! in_array($extensao, $regras['extensoes'], true)) {
            $responde('formato');
        }

        require_once ABSPATH.'wp-admin/includes/file.php';

        $movido = wp_handle_upload($enviado_arquivo, [
            'test_form' => false,
            'mimes' => [
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
        ]);

        if (isset($movido['error'])) {
            $responde('arquivo');
        }

        $anexos[] = $movido['file'];
    }

    $destino = get_theme_mod('conexao_email', get_option('admin_email'));

    $corpo = implode("\n", array_filter([
        'Nome: '.$nome,
        'E-mail: '.$email,
        $telefone ? 'Telefone: '.$telefone : '',
        $genero ? 'Gênero da obra: '.$genero : '',
        $obra ? 'Título provisório: '.$obra : '',
        $anexos ? 'Arquivo: '.basename($anexos[0]) : 'Sem arquivo anexado',
        '',
        $mensagem,
    ], static fn ($linha) => $linha !== ''));

    $enviado = wp_mail(
        $destino,
        sprintf('[Site] Novo manuscrito: %s', $obra ?: $nome),
        $corpo,
        [
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: '.$nome.' <'.$email.'>',
        ],
        $anexos
    );

    // o original não fica no servidor depois de enviado
    foreach ($anexos as $arquivo) {
        wp_delete_file($arquivo);
    }

    $responde($enviado ? 'ok' : 'erro');
}
