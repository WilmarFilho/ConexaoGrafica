<?php
/**
 * Limpeza: avisos, vitrines e pedidos de avaliação de terceiros que só fazem
 * barulho no painel. Tudo por filtro, em tempo de leitura: nenhuma opção dos
 * outros plugins é gravada. O CSS do admin.css cobre o que não tem filtro.
 */

if (! defined('ABSPATH')) {
    exit;
}

// ---- Rank Math -----------------------------------------------------------------

// pedido de avaliação, aviso do PRO e "novo tipo de post detectado"
add_filter('wp_helpers_notifications_render', function ($html, $mensagem, $opcoes) {
    $fora = ['rank_math_review_plugin_notice', 'rank_math_pro_notice', 'new_post_type'];

    return in_array(is_array($opcoes) ? ($opcoes['id'] ?? '') : '', $fora, true) ? '' : $html;
}, 99, 3);

// ---- Melhor Envio ----------------------------------------------------------------

// o banner "migre para o modo Integrador" some quando o usuário o dispensou;
// aqui ele conta como dispensado para todo mundo, sem gravar nada
add_filter('get_user_metadata', function ($valor, $usuario, $chave) {
    return $chave === 'melhor_envio_mode_notice_dismissed' ? [1] : $valor;
}, 10, 3);

// ---- WooCommerce -----------------------------------------------------------------

add_filter('woocommerce_allow_marketplace_suggestions', '__return_false');
add_filter('woocommerce_marketplace_suppress_promotions', '__return_true');
add_filter('woocommerce_helper_suppress_admin_notices', '__return_true');
add_filter('woocommerce_display_admin_footer_text', '__return_false');
add_filter('pre_option_woocommerce_show_marketplace_suggestions', fn (): string => 'no');

// lista de tarefas da configuração (Início do WooCommerce e Painel)
add_filter('pre_option_woocommerce_task_list_hidden', fn (): string => 'yes');
add_filter('pre_option_woocommerce_extended_task_list_hidden', fn (): string => 'yes');

// sem notas remotas novas na caixa de entrada do WooCommerce
add_filter('woocommerce_admin_features', function (array $recursos): array {
    return array_values(array_diff($recursos, ['remote-inbox-notifications']));
});

// ---- Cookie Notice (Compliance) --------------------------------------------------

add_filter('option_cookie_notice_options', function ($opcoes) {
    if (is_array($opcoes)) {
        $opcoes['review_notice'] = false;
        $opcoes['update_notice'] = false;
    }

    return $opcoes;
});

// ---- Avisos pendurados direto em admin_notices -----------------------------------

/** Tira de um gancho os métodos de uma classe (os plugins não guardam a instância em lugar acessível). */
function conexao_admin_remove_metodos(string $gancho, string $classe, array $metodos): void
{
    global $wp_filter;

    if (empty($wp_filter[$gancho])) {
        return;
    }

    foreach ($wp_filter[$gancho]->callbacks as $prioridade => $funcoes) {
        foreach ($funcoes as $funcao) {
            $f = $funcao['function'];

            if (is_array($f) && is_object($f[0]) && $f[0] instanceof $classe && in_array($f[1], $metodos, true)) {
                remove_action($gancho, $f, $prioridade);
            }
        }
    }
}

// in_admin_header roda antes de admin_notices
add_action('in_admin_header', function (): void {
    conexao_admin_remove_metodos('admin_notices', 'Cookie_Notice', ['display_notice', 'display_engine_notice']);
    conexao_admin_remove_metodos('admin_notices', 'Automattic\WooCommerce\Gateways\PayPal\Notices', ['add_paypal_notices']);
    conexao_admin_remove_metodos('admin_notices', 'MelhorEnvio\Http\Controllers\Admin\ModeNoticeController', ['renderNotice']);
});
