<?php
/**
 * Tela de login (wp-login.php) com a marca da editora: logo e nome no lugar do
 * WordPress, link para o site, sem seletor de idioma, "Perdeu a senha?" ao
 * lado de "Lembrar-me".
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('login_enqueue_scripts', function (): void {
    wp_enqueue_style('conexao-admin-fontes', CONEXAO_ADMIN_FONTES, [], null);
    wp_enqueue_style('conexao-admin-login', CONEXAO_ADMIN_URL.'assets/login.css', ['login', 'conexao-admin-fontes'], conexao_admin_versao_arquivo('/assets/login.css'));
});

add_filter('login_headerurl', fn (): string => home_url('/'));
add_filter('login_headertext', fn (): string => 'Conexão Editora');
add_filter('login_display_language_dropdown', '__return_false');

// "Acessar ‹ Conexão Editora", sem o "— WordPress"
add_filter('login_title', fn (string $titulo, string $acao): string => $acao.' ‹ '.get_bloginfo('name', 'display'), 10, 2);

// o link vai para dentro do formulário, na linha do "Lembrar-me"; o #nav original some por CSS
add_action('login_form', function (): void {
    printf('<p class="cxa-perdeu"><a href="%s">Perdeu a senha?</a></p>', esc_url(wp_lostpassword_url()));
});
