<?php
/**
 * Coleta de leads da ficha em PDF: quem quer abrir a ficha do livro ou do
 * capítulo deixa nome e e-mail (com o aceite da política de privacidade) e
 * recebe um link assinado, válido por uma hora, que entrega o PDF.
 *
 *   POST /wp-json/conexao-doi/v1/ficha   {post_id, nome, email, aceite, novidades}
 *   GET  /?conexao_ficha=ID&expira=T&assinatura=S   o PDF (link devolvido acima)
 *
 * Os PDFs ficam em uploads/conexao-fichas, com nome sorteado e acesso direto
 * bloqueado; a página não traz mais o endereço do arquivo.
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_DOI_LEADS_VERSAO = '1';
const CONEXAO_DOI_FICHAS_PASTA = 'conexao-fichas';
const CONEXAO_DOI_FICHA_VALIDADE = HOUR_IN_SECONDS;

function conexao_doi_leads_tabela(): string
{
    global $wpdb;

    return $wpdb->prefix.'conexao_doi_leads';
}

// tabela e pasta protegida: criadas na primeira carga depois do deploy
add_action('init', function (): void {
    if (get_option('conexao_doi_leads_versao') === CONEXAO_DOI_LEADS_VERSAO) {
        return;
    }

    global $wpdb;
    require_once ABSPATH.'wp-admin/includes/upgrade.php';

    dbDelta('CREATE TABLE '.conexao_doi_leads_tabela().' (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        criado_em datetime NOT NULL,
        nome varchar(160) NOT NULL,
        email varchar(190) NOT NULL,
        novidades tinyint(1) NOT NULL DEFAULT 0,
        aceite_em datetime NOT NULL,
        tipo varchar(20) NOT NULL,
        post_id bigint(20) unsigned NOT NULL,
        titulo varchar(500) NOT NULL,
        pagina varchar(500) NOT NULL DEFAULT \'\',
        ip_hash char(64) NOT NULL DEFAULT \'\',
        PRIMARY KEY  (id),
        KEY email (email),
        KEY post_id (post_id),
        KEY criado_em (criado_em)
    ) '.$wpdb->get_charset_collate().';');

    conexao_doi_fichas_protege_pasta();
    conexao_doi_fichas_move_antigas();

    update_option('conexao_doi_leads_versao', CONEXAO_DOI_LEADS_VERSAO);
}, 20);

/** Pasta das fichas com acesso direto negado (Apache/LiteSpeed lêem o .htaccess). */
function conexao_doi_fichas_protege_pasta(): string
{
    $base = wp_upload_dir(null, false);
    $pasta = trailingslashit($base['basedir']).CONEXAO_DOI_FICHAS_PASTA;

    wp_mkdir_p($pasta);

    if (! file_exists($pasta.'/.htaccess')) {
        file_put_contents($pasta.'/.htaccess', "# Conexão DOI: as fichas só saem pelo link assinado\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }

    if (! file_exists($pasta.'/index.php')) {
        file_put_contents($pasta.'/index.php', "<?php\n// Silêncio.\n");
    }

    return $pasta;
}

/**
 * Fichas gravadas antes da coleta (na pasta do mês, com nome previsível) vão
 * para a pasta protegida com nome sorteado.
 */
function conexao_doi_fichas_move_antigas(): void
{
    $pasta = conexao_doi_fichas_protege_pasta();
    $posts = get_posts([
        'post_type' => ['publicacao', 'capitulo'],
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);

    foreach ($posts as $post_id) {
        $dados = get_post_type($post_id) === 'publicacao'
            ? (array) (conexao_doi_dados_livro($post_id)['book'] ?? [])
            : conexao_doi_capitulo($post_id);

        foreach ((array) ($dados['documents'] ?? []) as $documento) {
            $anexo = (int) ($documento['attachment_id'] ?? 0);
            $arquivo = $anexo ? get_attached_file($anexo) : '';

            if (! $arquivo || ! file_exists($arquivo) || str_starts_with(wp_normalize_path($arquivo), wp_normalize_path($pasta))) {
                continue;
            }

            $novo = $pasta.'/'.conexao_doi_fichas_nome(basename($arquivo));

            if (@rename($arquivo, $novo)) {
                update_attached_file($anexo, $novo);
            }
        }
    }
}

/** "ficha-livro-12.pdf" → "ficha-livro-12-k3j9x0q2m1za.pdf": o nome não dá para adivinhar. */
function conexao_doi_fichas_nome(string $nome): string
{
    $nome = preg_replace('/\.pdf$/i', '', sanitize_file_name($nome)) ?: 'ficha';

    return $nome.'-'.strtolower(wp_generate_password(12, false)).'.pdf';
}

/** O post (livro ou capítulo) tem ficha em PDF para abrir? */
function conexao_doi_ficha_anexo(int $post_id): int
{
    $tipo = get_post_type($post_id);

    if ($tipo === 'publicacao') {
        $obra = (array) (conexao_doi_dados_livro($post_id)['book'] ?? []);
    } elseif ($tipo === 'capitulo') {
        $obra = conexao_doi_capitulo($post_id);
    } else {
        return 0;
    }

    foreach ((array) ($obra['documents'] ?? []) as $documento) {
        $anexo = (int) ($documento['attachment_id'] ?? 0);

        if ($anexo && get_post_type($anexo) === 'attachment') {
            return $anexo;
        }
    }

    return 0;
}

// ---------------------------------------------------------------- link assinado

function conexao_doi_ficha_assinatura(int $post_id, int $expira): string
{
    return hash_hmac('sha256', 'ficha|'.$post_id.'|'.$expira, wp_salt('auth'));
}

function conexao_doi_ficha_link(int $post_id): string
{
    $expira = time() + CONEXAO_DOI_FICHA_VALIDADE;

    return add_query_arg([
        'conexao_ficha' => $post_id,
        'expira' => $expira,
        'assinatura' => conexao_doi_ficha_assinatura($post_id, $expira),
    ], home_url('/'));
}

add_action('template_redirect', function (): void {
    if (! isset($_GET['conexao_ficha'])) {
        return;
    }

    $post_id = absint($_GET['conexao_ficha']);
    $expira = absint($_GET['expira'] ?? 0);
    $assinatura = sanitize_text_field(wp_unslash($_GET['assinatura'] ?? ''));

    if ($expira < time() || ! hash_equals(conexao_doi_ficha_assinatura($post_id, $expira), $assinatura)) {
        wp_die('Este link da ficha expirou. Volte à página do livro e abra a ficha de novo.', 'Link expirado', ['response' => 410, 'back_link' => true]);
    }

    $anexo = conexao_doi_ficha_anexo($post_id);
    $arquivo = $anexo ? get_attached_file($anexo) : '';

    if (! $arquivo || ! is_readable($arquivo)) {
        wp_die('A ficha desta obra não está disponível no momento.', 'Ficha indisponível', ['response' => 404, 'back_link' => true]);
    }

    $nome = sanitize_file_name('ficha-'.get_post_field('post_name', $post_id).'.pdf');

    nocache_headers();
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="'.$nome.'"');
    header('Content-Length: '.filesize($arquivo));
    header('X-Robots-Tag: noindex, nofollow');
    readfile($arquivo);
    exit;
});

// ---------------------------------------------------------------- cadastro

add_action('rest_api_init', function (): void {
    register_rest_route('conexao-doi/v1', '/ficha', [
        'methods' => 'POST',
        'callback' => 'conexao_doi_api_ficha',
        'permission_callback' => '__return_true',
        'args' => [
            'post_id' => ['required' => true, 'type' => 'integer'],
            'nome' => ['required' => true, 'type' => 'string'],
            'email' => ['required' => true, 'type' => 'string'],
            'aceite' => ['required' => true, 'type' => 'boolean'],
            'novidades' => ['type' => 'boolean', 'default' => false],
            'site' => ['type' => 'string', 'default' => ''],
        ],
    ]);
});

function conexao_doi_api_ficha(WP_REST_Request $pedido)
{
    // campo isca: robô preenche, gente não vê
    if (trim((string) $pedido['site']) !== '') {
        return new WP_Error('conexao_doi_ficha', 'Não foi possível registrar.', ['status' => 400]);
    }

    $post_id = (int) $pedido['post_id'];
    $nome = trim(sanitize_text_field((string) $pedido['nome']));
    $email = sanitize_email((string) $pedido['email']);

    if (mb_strlen($nome) < 2) {
        return new WP_Error('conexao_doi_ficha', 'Informe seu nome.', ['status' => 422]);
    }

    if (! is_email($email)) {
        return new WP_Error('conexao_doi_ficha', 'Informe um e-mail válido.', ['status' => 422]);
    }

    if (! $pedido['aceite']) {
        return new WP_Error('conexao_doi_ficha', 'É preciso aceitar a política de privacidade.', ['status' => 422]);
    }

    if (! in_array(get_post_type($post_id), ['publicacao', 'capitulo'], true) || get_post_status($post_id) !== 'publish' || ! conexao_doi_ficha_anexo($post_id)) {
        return new WP_Error('conexao_doi_ficha', 'A ficha desta obra não está disponível.', ['status' => 404]);
    }

    // até 20 fichas por hora por endereço de rede
    $ip_hash = hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), wp_salt('nonce'));
    $chave = 'conexao_ficha_'.substr($ip_hash, 0, 32);
    $vezes = (int) get_transient($chave);

    if ($vezes >= 20) {
        return new WP_Error('conexao_doi_ficha', 'Muitos pedidos seguidos. Tente de novo daqui a pouco.', ['status' => 429]);
    }

    set_transient($chave, $vezes + 1, HOUR_IN_SECONDS);

    global $wpdb;
    $agora = current_time('mysql');
    $referer = (string) wp_get_referer();

    $wpdb->insert(conexao_doi_leads_tabela(), [
        'criado_em' => $agora,
        'nome' => mb_substr($nome, 0, 160),
        'email' => mb_substr(strtolower($email), 0, 190),
        'novidades' => $pedido['novidades'] ? 1 : 0,
        'aceite_em' => $agora,
        'tipo' => get_post_type($post_id) === 'capitulo' ? 'capitulo' : 'livro',
        'post_id' => $post_id,
        'titulo' => mb_substr(html_entity_decode(get_the_title($post_id), ENT_QUOTES, 'UTF-8'), 0, 500),
        'pagina' => mb_substr(esc_url_raw($referer !== '' ? $referer : (string) get_permalink($post_id)), 0, 500),
        'ip_hash' => $ip_hash,
    ]);

    return new WP_REST_Response(['url' => conexao_doi_ficha_link($post_id)], 200);
}

// ---------------------------------------------------------------- wp-admin

add_action('admin_menu', function (): void {
    add_submenu_page(
        'edit.php?post_type=publicacao',
        'Leads das fichas',
        'Leads das fichas',
        'edit_others_posts',
        'conexao-doi-leads',
        'conexao_doi_leads_tela'
    );
});

function conexao_doi_leads_tela(): void
{
    global $wpdb;
    $tabela = conexao_doi_leads_tabela();
    $por_pagina = 50;
    $pagina = max(1, absint($_GET['paged'] ?? 1));
    $busca = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
    $onde = '1=1';
    $valores = [];

    if ($busca !== '') {
        $like = '%'.$wpdb->esc_like($busca).'%';
        $onde = '(nome LIKE %s OR email LIKE %s OR titulo LIKE %s)';
        $valores = [$like, $like, $like];
    }

    // phpcs:disable WordPress.DB.PreparedSQL
    $total = (int) $wpdb->get_var($valores ? $wpdb->prepare("SELECT COUNT(*) FROM {$tabela} WHERE {$onde}", $valores) : "SELECT COUNT(*) FROM {$tabela}");
    $linhas = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$tabela} WHERE {$onde} ORDER BY id DESC LIMIT %d OFFSET %d",
        array_merge($valores, [$por_pagina, ($pagina - 1) * $por_pagina])
    ));
    // phpcs:enable

    $csv = wp_nonce_url(add_query_arg(['action' => 'conexao_doi_leads_csv', 's' => $busca], admin_url('admin-post.php')), 'conexao_doi_leads_csv');
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Leads das fichas</h1>
        <a class="page-title-action" href="<?php echo esc_url($csv); ?>">Exportar CSV</a>
        <p>Quem abriu a ficha em PDF de um livro ou capítulo. "Novidades" indica quem aceitou receber e-mails da editora; só esses podem entrar em listas de divulgação.</p>

        <form method="get">
            <input type="hidden" name="post_type" value="publicacao">
            <input type="hidden" name="page" value="conexao-doi-leads">
            <p class="search-box">
                <label class="screen-reader-text" for="leads-busca">Buscar</label>
                <input type="search" id="leads-busca" name="s" value="<?php echo esc_attr($busca); ?>" placeholder="Nome, e-mail ou obra">
                <button class="button">Buscar</button>
            </p>
        </form>

        <table class="widefat striped">
            <thead><tr><th>Data</th><th>Nome</th><th>E-mail</th><th>Novidades</th><th>Obra</th><th>Tipo</th></tr></thead>
            <tbody>
                <?php if (! $linhas) : ?>
                    <tr><td colspan="6">Nenhum lead ainda.</td></tr>
                <?php endif; ?>
                <?php foreach ($linhas as $linha) : ?>
                    <tr>
                        <td><?php echo esc_html(mysql2date('d/m/Y H:i', $linha->criado_em)); ?></td>
                        <td><?php echo esc_html($linha->nome); ?></td>
                        <td><a href="<?php echo esc_url('mailto:'.$linha->email); ?>"><?php echo esc_html($linha->email); ?></a></td>
                        <td><?php echo $linha->novidades ? 'Sim' : 'Não'; ?></td>
                        <td><a href="<?php echo esc_url(get_permalink((int) $linha->post_id) ?: $linha->pagina); ?>" target="_blank" rel="noopener"><?php echo esc_html($linha->titulo); ?></a></td>
                        <td><?php echo $linha->tipo === 'capitulo' ? 'Capítulo' : 'Livro'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php
        $paginas = (int) ceil($total / $por_pagina);

        if ($paginas > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo paginate_links([ // phpcs:ignore WordPress.Security.EscapeOutput
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'current' => $pagina,
                'total' => $paginas,
            ]);
            echo '</div></div>';
        }
        ?>
        <p><?php echo esc_html(sprintf(_n('%d lead', '%d leads', $total, 'conexao-doi'), $total)); ?></p>
    </div>
    <?php
}

add_action('admin_post_conexao_doi_leads_csv', function (): void {
    if (! current_user_can('edit_others_posts')) {
        wp_die('Sem permissão.', '', ['response' => 403]);
    }

    check_admin_referer('conexao_doi_leads_csv');

    global $wpdb;
    $tabela = conexao_doi_leads_tabela();
    $busca = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));

    // phpcs:disable WordPress.DB.PreparedSQL
    if ($busca !== '') {
        $like = '%'.$wpdb->esc_like($busca).'%';
        $linhas = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$tabela} WHERE nome LIKE %s OR email LIKE %s OR titulo LIKE %s ORDER BY id DESC", $like, $like, $like), ARRAY_A);
    } else {
        $linhas = $wpdb->get_results("SELECT * FROM {$tabela} ORDER BY id DESC", ARRAY_A);
    }
    // phpcs:enable

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="leads-fichas-'.gmdate('Y-m-d').'.csv"');

    $saida = fopen('php://output', 'w');
    fwrite($saida, "\xEF\xBB\xBF"); // o Excel abre com acento certo
    fputcsv($saida, ['Data', 'Nome', 'E-mail', 'Aceita novidades', 'Obra', 'Tipo', 'Página'], ';');

    foreach ($linhas as $linha) {
        // evita fórmula do Excel em campo digitado pelo visitante
        $limpa = static fn (string $v): string => preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;

        fputcsv($saida, [
            mysql2date('d/m/Y H:i', $linha['criado_em']),
            $limpa($linha['nome']),
            $limpa($linha['email']),
            $linha['novidades'] ? 'Sim' : 'Não',
            $limpa($linha['titulo']),
            $linha['tipo'] === 'capitulo' ? 'Capítulo' : 'Livro',
            $linha['pagina'],
        ], ';');
    }

    fclose($saida);
    exit;
});
