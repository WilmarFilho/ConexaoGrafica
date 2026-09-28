<?php
/**
 * O leitor. Três endereços, todos atrás da checagem de acesso:
 *   /leitor/{produto}/                 a tela de leitura
 *   /leitor/{produto}/pdf/             o PDF, em fluxo (modelo novo)
 *   /leitor/{produto}/arquivo/{...}    os arquivos do flipbook (legado)
 * Nenhum arquivo de livro é entregue direto pelo servidor web.
 */

if (! defined('ABSPATH')) {
    exit;
}

function conexao_biblioteca_rotas(): void
{
    add_rewrite_rule('^leitor/([0-9]+)/pdf/?$', 'index.php?conexao_leitor=$matches[1]&conexao_leitor_parte=pdf', 'top');
    add_rewrite_rule('^leitor/([0-9]+)/arquivo/(.+)$', 'index.php?conexao_leitor=$matches[1]&conexao_leitor_arquivo=$matches[2]', 'top');
    add_rewrite_rule('^leitor/([0-9]+)/?$', 'index.php?conexao_leitor=$matches[1]', 'top');
}
add_action('init', 'conexao_biblioteca_rotas');

add_filter('query_vars', function (array $vars): array {
    return array_merge($vars, ['conexao_leitor', 'conexao_leitor_parte', 'conexao_leitor_arquivo']);
});

function conexao_biblioteca_url_leitor(int $produto_id, string $resto = ''): string
{
    return home_url('/leitor/'.conexao_biblioteca_produto_base($produto_id).'/'.ltrim($resto, '/'));
}

add_action('parse_request', function (WP $wp): void {
    $produto_id = (int) ($wp->query_vars['conexao_leitor'] ?? 0);

    if (! $produto_id) {
        return;
    }

    $parte = (string) ($wp->query_vars['conexao_leitor_parte'] ?? '');
    $arquivo = (string) ($wp->query_vars['conexao_leitor_arquivo'] ?? '');
    $e_tela = ($parte === '' && $arquivo === '');

    nocache_headers();
    header('X-Robots-Tag: noindex, nofollow', true);

    if (! is_user_logged_in()) {
        if (! $e_tela) {
            conexao_biblioteca_recusa(401, 'Entre na sua conta para ler este livro.');
        }

        // depois de entrar, o leitor volta para o livro que pediu
        setcookie('conexao_leitor_volta', (string) $produto_id, time() + 900, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
        $entrar = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url();
        wp_safe_redirect($entrar);
        exit;
    }

    $usuario_id = get_current_user_id();

    if (! conexao_biblioteca_pode_ler($usuario_id, $produto_id)) {
        conexao_biblioteca_recusa(403, 'Este livro não está na sua biblioteca.');
    }

    $livro = conexao_biblioteca_livro($produto_id);

    if ($parte === 'pdf') {
        if (! $livro['pdf']) {
            conexao_biblioteca_recusa(404, 'Arquivo não encontrado.');
        }

        conexao_biblioteca_entrega($livro['pdf'], 'application/pdf');
    }

    if ($arquivo !== '') {
        if (! $livro['flipbook']) {
            conexao_biblioteca_recusa(404, 'Arquivo não encontrado.');
        }

        $caminho = conexao_biblioteca_caminho_seguro($livro['flipbook'], $arquivo);
        $tipo = $caminho ? conexao_biblioteca_tipo($caminho) : '';

        if (! $caminho || ! $tipo) {
            conexao_biblioteca_recusa(404, 'Arquivo não encontrado.');
        }

        conexao_biblioteca_entrega($caminho, $tipo);
    }

    conexao_biblioteca_tela($produto_id, $livro);
});

/** Volta para o livro depois do login. */
add_filter('woocommerce_login_redirect', function (string $destino): string {
    $produto_id = isset($_COOKIE['conexao_leitor_volta']) ? absint($_COOKIE['conexao_leitor_volta']) : 0;

    if (! $produto_id) {
        return $destino;
    }

    setcookie('conexao_leitor_volta', '', time() - 3600, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);

    return conexao_biblioteca_url_leitor($produto_id);
}, 20);

/** Só o que um flipbook precisa; nada de .php, .zip ou arquivo de sistema. */
function conexao_biblioteca_tipo(string $caminho): string
{
    $tipos = [
        'html' => 'text/html; charset=UTF-8',
        'htm' => 'text/html; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'css' => 'text/css; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'xml' => 'application/xml; charset=UTF-8',
        'txt' => 'text/plain; charset=UTF-8',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
        'swf' => 'application/x-shockwave-flash',
    ];

    $extensao = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));

    return $tipos[$extensao] ?? '';
}

/** Entrega um arquivo, com suporte a intervalo (o leitor de PDF pede em pedaços). */
function conexao_biblioteca_entrega(string $caminho, string $tipo): void
{
    $tamanho = (int) filesize($caminho);
    $inicio = 0;
    $fim = $tamanho - 1;

    while (ob_get_level()) {
        ob_end_clean();
    }

    header_remove('Expires');
    header_remove('Pragma');
    header('Content-Type: '.$tipo);
    header('Content-Disposition: inline');
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');

    $intervalo = isset($_SERVER['HTTP_RANGE']) ? (string) $_SERVER['HTTP_RANGE'] : '';

    if ($intervalo && preg_match('/bytes=(\d*)-(\d*)/', $intervalo, $m)) {
        if ($m[1] === '' && $m[2] !== '') {
            $inicio = max(0, $tamanho - (int) $m[2]);
        } else {
            $inicio = (int) $m[1];
            $fim = ($m[2] !== '') ? min((int) $m[2], $tamanho - 1) : $fim;
        }

        if ($inicio > $fim || $inicio >= $tamanho) {
            status_header(416);
            header("Content-Range: bytes */$tamanho");
            exit;
        }

        status_header(206);
        header("Content-Range: bytes $inicio-$fim/$tamanho");
    } else {
        status_header(200);
    }

    header('Content-Length: '.($fim - $inicio + 1));

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
        exit;
    }

    $mao = fopen($caminho, 'rb');

    if (! $mao) {
        exit;
    }

    fseek($mao, $inicio);
    $resta = $fim - $inicio + 1;

    while ($resta > 0 && ! feof($mao) && ! connection_aborted()) {
        $bloco = fread($mao, (int) min(1048576, $resta));

        if ($bloco === false) {
            break;
        }

        echo $bloco;
        $resta -= strlen($bloco);
        flush();
    }

    fclose($mao);
    exit;
}

function conexao_biblioteca_recusa(int $codigo, string $mensagem): void
{
    status_header($codigo);
    header('Content-Type: text/html; charset=UTF-8');

    $biblioteca = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('biblioteca') : home_url('/');

    printf(
        '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>%1$s</title>'
        .'<body style="margin:0;display:grid;place-items:center;min-height:100vh;font:16px/1.5 system-ui,sans-serif;background:#F5F7F9;color:#1B2A33">'
        .'<main style="text-align:center;padding:24px"><p style="font-size:18px;margin:0 0 16px">%1$s</p>'
        .'<a href="%2$s" style="color:#0095D3">Ir para a minha biblioteca</a></main></body></html>',
        esc_html($mensagem),
        esc_url($biblioteca)
    );
    exit;
}

/** A tela de leitura: barra fina em cima, o livro ocupando o resto. */
function conexao_biblioteca_tela(int $produto_id, array $livro): void
{
    $usuario = wp_get_current_user();
    $titulo = get_the_title(conexao_biblioteca_produto_base($produto_id));
    $biblioteca = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('biblioteca') : home_url('/');

    if ($livro['modelo'] === 'pdf') {
        $origem = CONEXAO_BIBLIOTECA_URL.'assets/pdfjs/web/viewer.html?file='
            .rawurlencode(conexao_biblioteca_url_leitor($produto_id, 'pdf/'));
    } elseif ($livro['modelo'] === 'flipbook') {
        $origem = conexao_biblioteca_url_leitor($produto_id, 'arquivo/'.rawurlencode($livro['pagina']));
    } else {
        conexao_biblioteca_recusa(404, 'Este livro ainda está sendo preparado para leitura.');
    }

    $marca = trim($usuario->display_name.' · '.$usuario->user_email);

    status_header(200);
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html($titulo); ?> · Conexão Editora</title>
    <link rel="stylesheet" href="<?php echo esc_url(CONEXAO_BIBLIOTECA_URL.'assets/leitor.css?v='.CONEXAO_BIBLIOTECA_VERSAO); ?>">
</head>
<body class="leitor leitor--<?php echo esc_attr($livro['modelo']); ?>">
    <header class="leitor__barra">
        <a class="leitor__voltar" href="<?php echo esc_url($biblioteca); ?>">&larr; Minha biblioteca</a>
        <h1 class="leitor__titulo"><?php echo esc_html($titulo); ?></h1>
        <button class="leitor__tela-cheia" type="button" data-tela-cheia>Tela cheia</button>
    </header>

    <main class="leitor__palco">
        <iframe class="leitor__livro" src="<?php echo esc_url($origem); ?>" title="<?php echo esc_attr($titulo); ?>"
                allow="fullscreen" allowfullscreen data-livro data-modelo="<?php echo esc_attr($livro['modelo']); ?>"></iframe>

        <?php // identifica a cópia em tela: desestimula print e repasse ?>
        <div class="leitor__marca" aria-hidden="true" data-marca="<?php echo esc_attr($marca); ?>"></div>
    </main>

    <script src="<?php echo esc_url(CONEXAO_BIBLIOTECA_URL.'assets/leitor.js?v='.CONEXAO_BIBLIOTECA_VERSAO); ?>"></script>
</body>
</html>
    <?php
    exit;
}
