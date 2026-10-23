<?php
/**
 * Plugin Name: Conexão DOI
 * Description: Páginas permanentes de livro e capítulo (destino do DOI) publicadas pelo Hub Editora: API REST assinada, CPTs somente leitura, citações, SEO acadêmico e caixa de compra ligada ao WooCommerce.
 * Version: 1.1.3
 * Author: Conexão Pro
 * Requires PHP: 8.1
 * Requires at least: 6.5
 * Text Domain: conexao-doi
 */

if (! defined('ABSPATH')) {
    exit;
}

define('CONEXAO_DOI_VERSAO', '1.1.3');
define('CONEXAO_DOI_PASTA', __DIR__);
define('CONEXAO_DOI_URL', plugin_dir_url(__FILE__));

require_once __DIR__.'/inc/cpt.php';
require_once __DIR__.'/inc/dados.php';
require_once __DIR__.'/inc/api.php';
require_once __DIR__.'/inc/citacoes.php';
require_once __DIR__.'/inc/seo.php';
require_once __DIR__.'/inc/compra.php';
require_once __DIR__.'/inc/loja.php';
require_once __DIR__.'/inc/visual.php';

if (is_admin()) {
    require_once __DIR__.'/inc/admin.php';
}

register_activation_hook(__FILE__, function (): void {
    conexao_doi_registra_cpts();
    conexao_doi_regras_de_url();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

// atualização de versão sem reativar (o deploy só troca os arquivos)
add_action('init', function (): void {
    if (get_option('conexao_doi_versao') === CONEXAO_DOI_VERSAO) {
        return;
    }

    flush_rewrite_rules();
    update_option('conexao_doi_versao', CONEXAO_DOI_VERSAO);
}, 99);

/** Versão de um arquivo do plugin pela data de gravação, para o cache do navegador. */
function conexao_doi_versao_arquivo(string $relativo): string
{
    $caminho = CONEXAO_DOI_PASTA.$relativo;

    return file_exists($caminho) ? (string) filemtime($caminho) : CONEXAO_DOI_VERSAO;
}
