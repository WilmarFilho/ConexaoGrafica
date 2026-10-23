<?php
/**
 * Marca do painel: esquema de cores próprio (padrão para todos), fontes,
 * folha de estilo e script do tema, barra do topo e rodapé.
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_ADMIN_ESQUEMA = 'conexao';

// esquema de cores "Conexão Editora": a folha dele só zera o que o esquema do
// núcleo pintaria; o visual inteiro vem do admin.css, carregado em todas as telas
add_action('admin_init', function (): void {
    wp_admin_css_color(
        CONEXAO_ADMIN_ESQUEMA,
        'Conexão Editora',
        CONEXAO_ADMIN_URL.'assets/esquema.css',
        ['#0A0A0A', '#111111', '#5B9BFF', '#8DB8FF'],
        ['base' => '#C9C9C9', 'focus' => '#8DB8FF', 'current' => '#8DB8FF']
    );
}, 1);

// é o esquema de todo mundo: o seletor do perfil sai, a escolha antiga é ignorada
add_filter('get_user_option_admin_color', fn (): string => CONEXAO_ADMIN_ESQUEMA);
add_action('admin_init', function (): void {
    remove_action('admin_color_scheme_picker', 'admin_color_scheme_picker');
});

add_action('admin_enqueue_scripts', function (): void {
    wp_enqueue_style('conexao-admin-fontes', CONEXAO_ADMIN_FONTES, [], null);
    wp_enqueue_style('conexao-admin', CONEXAO_ADMIN_URL.'assets/admin.css', ['conexao-admin-fontes'], conexao_admin_versao_arquivo('/assets/admin.css'));
    wp_enqueue_script('conexao-admin', CONEXAO_ADMIN_URL.'assets/admin.js', [], conexao_admin_versao_arquivo('/assets/admin.js'), true);
}, 99);

add_filter('admin_body_class', fn (string $classes): string => $classes.' cxa ');

// a área de texto do editor clássico (iframe do TinyMCE) também fica escura
add_filter('mce_css', function (string $folhas): string {
    $nossa = CONEXAO_ADMIN_URL.'assets/editor.css?ver='.conexao_admin_versao_arquivo('/assets/editor.css');

    return $folhas === '' ? $nossa : $folhas.','.$nossa;
});

// a barra do topo começa pela marca, sem o logo do WordPress
add_action('admin_bar_menu', function (WP_Admin_Bar $barra): void {
    $barra->remove_node('wp-logo');
}, 99);

add_filter('admin_footer_text', fn (): string => '<span class="cxa-rodape">Conexão Editora · painel do site</span>', 99);

add_filter('update_footer', function (): string {
    $partes = ['WordPress '.get_bloginfo('version')];

    if (defined('WC_VERSION')) {
        $partes[] = 'WooCommerce '.WC_VERSION;
    }

    return '<span class="cxa-rodape cxa-mono">'.esc_html(implode(' · ', $partes)).'</span>';
}, 99);
