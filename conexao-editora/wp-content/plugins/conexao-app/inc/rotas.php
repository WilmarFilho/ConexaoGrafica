<?php
/**
 * Rotas do app em /wp-json/conexao-app/v1. Login, cadastro e esqueci a senha
 * são abertos; todo o resto passa por conexao_app_autentica (token de aparelho).
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function (): void {
    $ns = CONEXAO_APP_NS;
    $aberta = '__return_true';
    $token = 'conexao_app_autentica';

    $rotas = [
        ['/login', 'POST', 'conexao_app_rota_login', $aberta],
        ['/cadastro', 'POST', 'conexao_app_rota_cadastro', $aberta],
        ['/senha', 'POST', 'conexao_app_rota_senha', $aberta],
        ['/sair', 'POST', 'conexao_app_rota_sair', $token],
        ['/eu', 'GET', 'conexao_app_rota_eu', $token],
        ['/aparelhos', 'GET', 'conexao_app_rota_aparelhos', $token],
        ['/aparelhos/(?P<id>\d{1,18})', 'DELETE', 'conexao_app_rota_desconecta', $token],
        ['/estante', 'GET', 'conexao_app_rota_estante', $token],
        ['/livros/(?P<id>\d{1,18})', 'GET', 'conexao_app_rota_livro', $token],
        ['/livros/(?P<id>\d{1,18})/pdf', ['GET', 'HEAD'], 'conexao_app_rota_pdf', $token],
        ['/livros/(?P<id>\d{1,18})/leitura', 'GET', 'conexao_app_rota_leitura', $token],
        ['/livros/(?P<id>\d{1,18})/leitura', 'PUT', 'conexao_app_rota_salva_leitura', $token],
        ['/livros/(?P<id>\d{1,18})/marcadores', 'POST', 'conexao_app_rota_marca', $token],
        ['/livros/(?P<id>\d{1,18})/marcadores/(?P<marcador>\d{1,18})', 'DELETE', 'conexao_app_rota_desmarca', $token],
        ['/livros/(?P<id>\d{1,18})/acesso-web', 'POST', 'conexao_app_rota_acesso_web', $token],
        ['/email/codigo', 'POST', 'conexao_app_rota_email_codigo', $token],
        ['/email/confirmar', 'POST', 'conexao_app_rota_email_confirmar', $token],
        ['/pedidos', 'GET', 'conexao_app_rota_pedidos', $token],
        ['/pedidos/(?P<ref>[A-Za-z0-9_-]{1,60})', 'GET', 'conexao_app_rota_pedido', $token],
    ];

    $por_rota = [];

    foreach ($rotas as [$rota, $metodos, $callback, $permissao]) {
        $por_rota[$rota][] = ['methods' => $metodos, 'callback' => $callback, 'permission_callback' => $permissao];
    }

    foreach ($por_rota as $rota => $manipuladores) {
        register_rest_route($ns, $rota, $manipuladores);
    }
});
