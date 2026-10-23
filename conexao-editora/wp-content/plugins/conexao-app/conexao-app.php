<?php
/**
 * Plugin Name: Conexão App
 * Description: Servidor do app do leitor (web, Android e iOS): login por token de aparelho, estante com os livros da biblioteca, PDF, progresso e marcadores sincronizados e pedidos de todos os canais (Hub Editora). Contrato em docs/app-leitor-contrato.md.
 * Version: 1.1.0
 * Author: Conexão Pro
 * Requires PHP: 8.1
 * Requires at least: 6.5
 * Text Domain: conexao-app
 */

if (! defined('ABSPATH')) {
    exit;
}

define('CONEXAO_APP_VERSAO', '1.1.0');
define('CONEXAO_APP_NS', 'conexao-app/v1');

// o Hub é o mesmo em todos os ambientes; o segredo fica só no wp-config
if (! defined('CONEXAO_APP_HUB_URL')) {
    define('CONEXAO_APP_HUB_URL', 'https://hub.pubcon.com.br');
}

require_once __DIR__.'/inc/tabelas.php';
require_once __DIR__.'/inc/respostas.php';
require_once __DIR__.'/inc/aparelhos.php';
require_once __DIR__.'/inc/conta.php';
require_once __DIR__.'/inc/livros.php';
require_once __DIR__.'/inc/leitura.php';
require_once __DIR__.'/inc/pedidos.php';
require_once __DIR__.'/inc/email.php';
require_once __DIR__.'/inc/acesso-web.php';
require_once __DIR__.'/inc/rotas.php';

register_activation_hook(__FILE__, function (): void {
    conexao_app_cria_tabelas();
    update_option('conexao_app_versao', CONEXAO_APP_VERSAO, false);
});

// o deploy só troca os arquivos: versão nova migra as tabelas na primeira carga
add_action('plugins_loaded', function (): void {
    if (get_option('conexao_app_versao') === CONEXAO_APP_VERSAO) {
        return;
    }

    conexao_app_cria_tabelas();
    update_option('conexao_app_versao', CONEXAO_APP_VERSAO, false);
});
