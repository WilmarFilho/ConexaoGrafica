<?php
/**
 * Onde os livros ficam guardados. A pasta deve morar fora da raiz pública do
 * site; quando isso não for possível, um .htaccess fecha o acesso direto.
 */

if (! defined('ABSPATH')) {
    exit;
}

/** Raiz da biblioteca, sem barra no fim. */
function conexao_biblioteca_dir(): string
{
    if (defined('CONEXAO_BIBLIOTECA_DIR') && CONEXAO_BIBLIOTECA_DIR) {
        return rtrim((string) CONEXAO_BIBLIOTECA_DIR, '/\\');
    }

    $ambiente = getenv('CONEXAO_BIBLIOTECA_DIR');

    if ($ambiente) {
        return rtrim($ambiente, '/\\');
    }

    return WP_CONTENT_DIR.'/biblioteca-privada';
}

/** Subpasta da biblioteca: flipbooks, pdf ou envio (temporários). */
function conexao_biblioteca_subpasta(string $nome): string
{
    return conexao_biblioteca_dir().'/'.$nome;
}

function conexao_biblioteca_prepara_pasta(): void
{
    foreach (['', 'flipbooks', 'pdf', 'envio'] as $sub) {
        $pasta = rtrim(conexao_biblioteca_dir().'/'.$sub, '/');

        if (! is_dir($pasta)) {
            wp_mkdir_p($pasta);
        }
    }

    // se a pasta acabar dentro da raiz pública, o Apache recusa entregar
    $trava = conexao_biblioteca_dir().'/.htaccess';

    if (! file_exists($trava)) {
        @file_put_contents($trava, "Require all denied\nDeny from all\n");
    }

    $indice = conexao_biblioteca_dir().'/index.php';

    if (! file_exists($indice)) {
        @file_put_contents($indice, "<?php // silêncio\n");
    }
}

/**
 * Caminho real de um arquivo dentro de uma pasta-base, ou vazio se o pedido
 * tentar sair dela (../) ou o arquivo não existir.
 */
function conexao_biblioteca_caminho_seguro(string $base, string $relativo): string
{
    $base_real = realpath($base);

    if ($base_real === false) {
        return '';
    }

    $alvo = realpath($base_real.'/'.ltrim(str_replace('\\', '/', $relativo), '/'));

    if ($alvo === false || ! is_file($alvo)) {
        return '';
    }

    $base_real = rtrim(str_replace('\\', '/', $base_real), '/').'/';
    $alvo_normal = str_replace('\\', '/', $alvo);

    return str_starts_with($alvo_normal, $base_real) ? $alvo : '';
}

function conexao_biblioteca_cria_tabela(): void
{
    global $wpdb;

    require_once ABSPATH.'wp-admin/includes/upgrade.php';

    // acessos concedidos fora de um pedido da loja: cortesia, outro canal de
    // venda, migração. A compra na loja não precisa de linha aqui.
    dbDelta("CREATE TABLE {$wpdb->prefix}conexao_biblioteca_acessos (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        usuario_id bigint(20) unsigned NOT NULL,
        produto_id bigint(20) unsigned NOT NULL,
        origem varchar(40) NOT NULL DEFAULT 'manual',
        referencia varchar(120) NOT NULL DEFAULT '',
        criado_em datetime NOT NULL,
        expira_em datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY usuario_produto_origem (usuario_id, produto_id, origem, referencia),
        KEY produto (produto_id)
    ) {$wpdb->get_charset_collate()};");
}
