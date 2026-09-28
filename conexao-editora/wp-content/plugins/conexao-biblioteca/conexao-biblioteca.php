<?php
/**
 * Plugin Name: Conexão Biblioteca
 * Description: Livros digitais da Conexão Editora: arquivo protegido (PDF ou flipbook legado), leitura só para quem comprou e a biblioteca do leitor na conta.
 * Version: 1.0.0
 * Author: Conexão Pro
 * Requires PHP: 8.1
 * Text Domain: conexao-biblioteca
 */

if (! defined('ABSPATH')) {
    exit;
}

define('CONEXAO_BIBLIOTECA_VERSAO', '1.0.0');
define('CONEXAO_BIBLIOTECA_PASTA', __DIR__);
define('CONEXAO_BIBLIOTECA_URL', plugin_dir_url(__FILE__));

require_once __DIR__.'/inc/armazenamento.php';
require_once __DIR__.'/inc/livros.php';
require_once __DIR__.'/inc/acesso.php';
require_once __DIR__.'/inc/leitor.php';
require_once __DIR__.'/inc/conta.php';

if (is_admin()) {
    require_once __DIR__.'/inc/admin-envio.php';
    require_once __DIR__.'/inc/admin-produto.php';
    require_once __DIR__.'/inc/admin-lista.php';
}

register_activation_hook(__FILE__, function (): void {
    conexao_biblioteca_cria_tabela();
    conexao_biblioteca_prepara_pasta();
    conexao_biblioteca_rotas();
    conexao_biblioteca_rota_conta();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

// atualização de versão sem reativar o plugin (o deploy troca só os arquivos)
add_action('init', function (): void {
    if (get_option('conexao_biblioteca_versao') === CONEXAO_BIBLIOTECA_VERSAO) {
        return;
    }

    conexao_biblioteca_cria_tabela();
    conexao_biblioteca_prepara_pasta();
    flush_rewrite_rules();
    update_option('conexao_biblioteca_versao', CONEXAO_BIBLIOTECA_VERSAO);
}, 99);
