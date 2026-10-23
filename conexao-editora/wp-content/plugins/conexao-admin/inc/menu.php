<?php
/**
 * Menu do painel: Rank Math, Compliance e Melhor Envio (legado) vão para o
 * fim, num grupo "Mais" que o admin.js recolhe. Os upsells do Rank Math saem
 * do submenu. Os ícones (traço fino) vêm do admin.css.
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_ADMIN_MENU_MAIS = ['rank-math', 'cookie-notice', 'melhor-envio'];

add_filter('custom_menu_order', '__return_true');

// roda depois do WooCommerce: o que é do grupo "Mais" vai para depois do último separador
add_filter('menu_order', function (array $ordem): array {
    $mais = array_values(array_intersect($ordem, CONEXAO_ADMIN_MENU_MAIS));

    if (! $mais || ! in_array('separator-last', $ordem, true)) {
        return $ordem;
    }

    $ordem = array_values(array_diff($ordem, $mais, ['separator-last']));

    return array_merge($ordem, ['separator-last'], $mais);
}, 99);

add_action('admin_menu', function (): void {
    global $menu, $submenu;

    foreach ((array) $menu as $chave => $item) {
        if (($item[2] ?? '') === 'separator-last') {
            $menu[$chave][4] = ($item[4] ?? '').' cxa-mais';
        }
    }

    // Rank Math: sem "Liberar o PRO" e sem as vitrines de IA
    $upsells = ['rank-math-ai-visibility', 'rank-math-content-ai-page', 'rank-math-links-page'];

    foreach ((array) ($submenu['rank-math'] ?? []) as $chave => $item) {
        $slug = (string) ($item[2] ?? '');

        if (in_array($slug, $upsells, true) || str_contains($slug, 'rankmath.com/pricing') || str_contains($slug, 'utm_campaign=Upgrade')) {
            unset($submenu['rank-math'][$chave]);
        }
    }
}, 9999);
