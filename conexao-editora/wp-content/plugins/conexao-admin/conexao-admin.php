<?php
/**
 * Plugin Name: Conexão Admin
 * Description: Tema do painel da Conexão Editora (linguagem ASTER, escuro): cores, fontes, menu, login, Painel com widgets próprios, lista e edição de produtos e limpeza dos avisos de terceiros.
 * Version: 1.0.0
 * Author: Conexão Pro
 * Requires PHP: 8.1
 * Requires at least: 6.5
 * Text Domain: conexao-admin
 */

if (! defined('ABSPATH')) {
    exit;
}

define('CONEXAO_ADMIN_VERSAO', '1.0.0');
define('CONEXAO_ADMIN_PASTA', __DIR__);
define('CONEXAO_ADMIN_URL', plugin_dir_url(__FILE__));
define('CONEXAO_ADMIN_FONTES', 'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@1,9..144,300;1,9..144,400&family=Inter:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap');

require_once __DIR__.'/inc/login.php';

if (is_admin()) {
    require_once __DIR__.'/inc/marca.php';
    require_once __DIR__.'/inc/menu.php';
    require_once __DIR__.'/inc/limpeza.php';
    require_once __DIR__.'/inc/painel-dados.php';
    require_once __DIR__.'/inc/painel.php';
    require_once __DIR__.'/inc/produtos.php';
}

/** Versão de um arquivo do plugin pela data de gravação, para o cache do navegador. */
function conexao_admin_versao_arquivo(string $relativo): string
{
    $caminho = CONEXAO_ADMIN_PASTA.$relativo;

    return file_exists($caminho) ? (string) filemtime($caminho) : CONEXAO_ADMIN_VERSAO;
}
