<?php
/**
 * Modo claro / escuro do painel, por usuário. O padrão é o escuro; a escolha
 * só é gravada (user meta cxa_tema) quando a própria pessoa clica no botão da
 * barra do topo. A classe vai no <body> já no servidor, para não piscar; o
 * admin.js só troca a classe na hora do clique e salva.
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_ADMIN_META_TEMA = 'cxa_tema';

/** 'claro' ou 'escuro' para o usuário (padrão: escuro). */
function conexao_admin_tema(int $usuario_id = 0): string
{
    $usuario_id = $usuario_id ?: get_current_user_id();
    $tema = $usuario_id ? (string) get_user_meta($usuario_id, CONEXAO_ADMIN_META_TEMA, true) : '';
    $tema = (string) apply_filters('conexao_admin_tema', $tema, $usuario_id);

    return $tema === 'claro' ? 'claro' : 'escuro';
}

add_filter('admin_body_class', function (string $classes): string {
    return conexao_admin_tema() === 'claro' ? $classes.' cxa-claro ' : $classes;
}, 20);

// a área de texto do editor clássico (iframe) segue o modo
add_filter('tiny_mce_before_init', function (array $config): array {
    if (conexao_admin_tema() === 'claro') {
        $config['body_class'] = trim(($config['body_class'] ?? '').' cxa-claro');
    }

    return $config;
});

// botão na barra do topo, à direita, ao lado do menu do usuário
add_action('admin_bar_menu', function (WP_Admin_Bar $barra): void {
    if (! is_admin() || ! is_user_logged_in()) {
        return;
    }

    $claro = conexao_admin_tema() === 'claro';

    $barra->add_node([
        'id' => 'cxa-tema',
        'parent' => 'top-secondary',
        'title' => sprintf(
            '<span class="ab-icon cxa-tema__icone" aria-hidden="true"></span><span class="ab-label cxa-tema__rotulo">%s</span>',
            $claro ? 'Modo escuro' : 'Modo claro'
        ),
        'href' => '#',
        'meta' => [
            'class' => 'cxa-tema',
            'title' => 'Alternar entre o modo claro e o escuro do painel',
        ],
    ]);
}, 100);

add_action('admin_enqueue_scripts', function (): void {
    wp_localize_script('conexao-admin', 'cxaTema', [
        'ajax' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('conexao_admin_tema'),
        'atual' => conexao_admin_tema(),
    ]);
}, 100);

// grava a escolha: só a própria pessoa, só com nonce, só 'claro' ou 'escuro'
add_action('wp_ajax_conexao_admin_tema', function (): void {
    check_ajax_referer('conexao_admin_tema', 'nonce');

    if (! current_user_can('read')) {
        wp_send_json_error(['mensagem' => 'Sem permissão.'], 403);
    }

    $tema = isset($_POST['tema']) ? sanitize_key(wp_unslash($_POST['tema'])) : '';

    if (! in_array($tema, ['claro', 'escuro'], true)) {
        wp_send_json_error(['mensagem' => 'Modo inválido.'], 400);
    }

    update_user_meta(get_current_user_id(), CONEXAO_ADMIN_META_TEMA, $tema);

    wp_send_json_success(['tema' => $tema]);
});
