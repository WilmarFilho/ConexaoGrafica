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

// avisos da loja (pedido novo, cancelado, falhou; cliente novo; senha trocada)
// vão para o comercial, não para o e-mail do administrador do WordPress
foreach (['new_order', 'cancelled_order', 'failed_order'] as $aviso) {
    add_filter('woocommerce_email_recipient_'.$aviso, fn () => conexao_email_resposta(), 99);
}

add_filter('wp_new_user_notification_email_admin', function (array $mensagem): array {
    $mensagem['to'] = conexao_email_resposta();

    return $mensagem;
});

add_filter('wp_password_change_notification_email', function (array $mensagem): array {
    $mensagem['to'] = conexao_email_resposta();

    return $mensagem;
});

// {store_email} nos textos do WooCommerce é o remetente, que não tem caixa: mostra o comercial
add_filter('woocommerce_email_format_string', fn ($texto) => is_string($texto) ? str_replace(conexao_email_remetente(), conexao_email_resposta(), $texto) : $texto);

// envelope = remetente do domínio (o SPF é conferido por ele); fora o Mailpit do ambiente local
add_action('phpmailer_init', function ($phpmailer) {
    if (getenv('CONEXAO_SMTP_HOST')) {
        return;
    }

    if (str_ends_with(strtolower((string) $phpmailer->From), '@conexaoeditora.com.br')) {
        $phpmailer->Sender = $phpmailer->From;
    }
}, 20);

// ---------------------------------------------------------------- visual dos e-mails

/*
 * E-mails do WooCommerce na identidade da loja: logo, azul da marca, Nunito,
 * cartão branco sobre fundo azulado e rodapé com os atalhos da conta. Fica na
 * estrutura do próprio WooCommerce (só cores, logo, estilo e rodapé mudam),
 * então os modelos continuam valendo quando ele atualizar.
 */
const CONEXAO_EMAIL_CORES = [
    'woocommerce_email_base_color' => '#0095D3',
    'woocommerce_email_background_color' => '#EAF4FB',
    'woocommerce_email_body_background_color' => '#FFFFFF',
    'woocommerce_email_text_color' => '#1F2A33',
    'woocommerce_email_footer_text_color' => '#55606B',
];

foreach (CONEXAO_EMAIL_CORES as $opcao => $cor) {
    add_filter('pre_option_'.$opcao, static fn () => $cor);
}

add_filter('pre_option_woocommerce_email_header_image', fn () => get_template_directory_uri().'/assets/img/logo.png');
add_filter('pre_option_woocommerce_email_header_image_width', fn () => '190');
add_filter('pre_option_woocommerce_email_header_alignment', fn () => 'left');
add_filter('pre_option_woocommerce_email_font_family', fn () => "Nunito, 'Segoe UI', Helvetica, Arial, sans-serif");

add_filter('woocommerce_email_styles', function (string $css): string {
    return $css.'
        body, #outer_wrapper { background-color: #EAF4FB !important; }
        #wrapper { padding: 32px 0 24px !important; }
        #inner_wrapper { border-radius: 16px !important; box-shadow: 0 6px 20px rgba(15, 45, 70, .08) !important; }
        #template_header_image { padding: 30px 40px 0 !important; }
        #template_header_image img { width: 190px !important; height: auto !important; }
        #template_container { border: 0 !important; border-radius: 0 !important; box-shadow: none !important; }
        #template_header { background-color: #FFFFFF !important; border-radius: 0 !important; border-bottom: 4px solid #0095D3 !important; }
        #header_wrapper { padding: 22px 40px 20px !important; }
        #header_wrapper h1, h1 { color: #00448B !important; font-size: 26px !important; font-weight: 800 !important; line-height: 1.25 !important; text-shadow: none !important; }
        #body_content_inner_cell { padding: 30px 40px 36px !important; }
        #body_content_inner, #body_content_inner p, #body_content td { color: #1F2A33 !important; font-size: 15px !important; line-height: 1.6 !important; }
        h2 { color: #00448B !important; font-size: 18px !important; font-weight: 800 !important; }
        h3 { color: #00448B !important; font-size: 15px !important; }
        a, .link { color: #0095D3 !important; font-weight: 700; }
        .email-order-details td, .email-order-details th, .td { border-color: #E1E6EA !important; }
        .order-totals-total th, .order-totals-total td { color: #00448B !important; }
        address { border: 1px solid #E1E6EA !important; border-radius: 10px !important; padding: 14px 16px !important; color: #55606B !important; }
        .button, a.button, .email-button { background-color: #0095D3 !important; border-color: #0095D3 !important; color: #FFFFFF !important; border-radius: 999px !important; padding: 10px 18px !important; font-weight: 800 !important; font-size: 13px !important; line-height: 1.3 !important; display: inline-block !important; white-space: nowrap; }
        #template_footer #credit, #template_footer #credit p { color: #55606B !important; font-size: 12.5px !important; line-height: 1.6 !important; }
        #template_footer #credit a { color: #0095D3 !important; }
        .conexao-email-atalhos { margin: 26px 0 0; padding: 18px 20px; border-radius: 12px; background-color: #EAF4FB; }
        .conexao-email-atalhos p { margin: 0 0 10px !important; color: #1F2A33 !important; font-size: 14px !important; }
        .conexao-email-atalhos a.conexao-email-botao { display: inline-block; margin: 4px 8px 0 0; padding: 10px 18px; border-radius: 999px; background-color: #0095D3; color: #FFFFFF !important; font-size: 13px !important; text-decoration: none; }
        .conexao-email-atalhos a.conexao-email-botao--contorno { background-color: #FFFFFF; color: #0095D3 !important; border: 1px solid #0095D3; }
    ';
});

// "Boas-vindas ao Conexão Editora" da tradução: a editora é feminina
add_filter('woocommerce_email_heading_customer_new_account', fn () => 'Boas-vindas à Conexão Editora');

// fim do e-mail do cliente: atalhos para a biblioteca e os pedidos
add_action('woocommerce_email_footer', function ($email): void {
    if (! $email || ! method_exists($email, 'is_customer_email') || ! $email->is_customer_email()) {
        return;
    }

    $conta = wc_get_page_permalink('myaccount');
    $biblioteca = function_exists('wc_get_account_endpoint_url') && array_key_exists('biblioteca', wc_get_account_menu_items())
        ? wc_get_account_endpoint_url('biblioteca')
        : '';
    ?>
    <div class="conexao-email-atalhos">
        <p><strong>Seus livros digitais</strong> ficam na sua biblioteca, no site e no app Conexão Editora (Android e iPhone), com o mesmo e-mail e senha.</p>
        <?php if ($biblioteca) : ?>
            <a class="conexao-email-botao" href="<?php echo esc_url($biblioteca); ?>">Minha biblioteca</a>
        <?php endif; ?>
        <a class="conexao-email-botao conexao-email-botao--contorno" href="<?php echo esc_url($conta); ?>">Minha conta</a>
    </div>
    <?php
}, 5);

add_filter('woocommerce_email_footer_text', function (): string {
    $site = home_url('/');

    return '<strong>Conexão Editora</strong> · Conhecimento que transforma<br>'
        .'Dúvidas? Responda este e-mail ou fale com a gente pelo <a href="'.esc_url(home_url('/contato/')).'">site</a>.<br>'
        .'<a href="'.esc_url($site).'">conexaoeditora.com.br</a>';
});
