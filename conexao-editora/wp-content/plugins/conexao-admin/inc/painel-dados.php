<?php
/**
 * Dados dos widgets do Painel. Só leitura; o que pesa (pedidos e estoque) fica
 * em cache curto (transient de 5 minutos).
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_ADMIN_CACHE = 5 * MINUTE_IN_SECONDS;

/** Estados de pedido pago, no formato da tabela de estatísticas (wc-…). */
function conexao_admin_estados_pagos(): array
{
    $estados = function_exists('wc_get_is_paid_statuses') ? wc_get_is_paid_statuses() : ['processing', 'completed'];

    return array_map(static fn ($e) => 'wc-'.$e, $estados);
}

/** Loja hoje: vendas do dia, pedidos a expedir, estoque baixo e os pedidos pagos dos últimos 14 dias. */
function conexao_admin_dados_loja(): array
{
    $dados = get_transient('conexao_admin_loja');

    if (is_array($dados)) {
        return $dados;
    }

    global $wpdb;

    $estados = conexao_admin_estados_pagos();
    $marcas = implode(',', array_fill(0, count($estados), '%s'));
    $hoje = current_time('Y-m-d');
    $inicio = wp_date('Y-m-d', strtotime($hoje.' -13 days'));
    $stats = $wpdb->prefix.'wc_order_stats';

    $vendas = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) AS pedidos, COALESCE(SUM(total_sales), 0) AS total
         FROM {$stats}
         WHERE parent_id = 0 AND status IN ($marcas) AND date_created >= %s",
        ...[...$estados, $hoje.' 00:00:00']
    ));

    $por_dia = $wpdb->get_results($wpdb->prepare(
        "SELECT DATE(date_created) AS dia, COUNT(*) AS pedidos
         FROM {$stats}
         WHERE parent_id = 0 AND status IN ($marcas) AND date_created >= %s
         GROUP BY DATE(date_created)",
        ...[...$estados, $inicio.' 00:00:00']
    ), OBJECT_K);

    $serie = [];

    for ($i = 13; $i >= 0; $i--) {
        $dia = wp_date('Y-m-d', strtotime($hoje.' -'.$i.' days'));
        $serie[$dia] = (int) ($por_dia[$dia]->pedidos ?? 0);
    }

    // estoque baixo: estoque controlado, acima do "sem estoque" e até o limite da loja
    $limite = (int) get_option('woocommerce_notify_low_stock_amount', 2);
    $zero = (int) get_option('woocommerce_notify_no_stock_amount', 0);
    $estoque_baixo = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}wc_product_meta_lookup l
         JOIN {$wpdb->posts} p ON p.ID = l.product_id
         WHERE p.post_status = 'publish' AND p.post_type IN ('product', 'product_variation')
           AND l.stock_quantity IS NOT NULL AND l.stock_quantity > %d AND l.stock_quantity <= %d",
        $zero,
        $limite
    ));

    $dados = [
        'vendas_total' => (float) ($vendas->total ?? 0),
        'vendas_pedidos' => (int) ($vendas->pedidos ?? 0),
        'processando' => function_exists('wc_orders_count') ? (int) wc_orders_count('processing') : 0,
        'estoque_baixo' => $estoque_baixo,
        'limite_estoque' => $limite,
        'serie' => $serie,
    ];

    set_transient('conexao_admin_loja', $dados, CONEXAO_ADMIN_CACHE);

    return $dados;
}

/** Ligação com o Hub Editora: webhooks de pedido, último acesso da chave da API e publicações DOI recebidas. */
function conexao_admin_dados_hub(): array
{
    $dados = get_transient('conexao_admin_hub');

    if (is_array($dados)) {
        return $dados;
    }

    global $wpdb;

    $webhooks = $wpdb->get_results(
        "SELECT name, status, delivery_url, failure_count FROM {$wpdb->prefix}wc_webhooks
         WHERE name LIKE '%Hub%' OR delivery_url LIKE '%hub.%'"
    );

    $chave = $wpdb->get_var(
        "SELECT last_access FROM {$wpdb->prefix}woocommerce_api_keys
         WHERE description LIKE '%Hub%' ORDER BY last_access DESC LIMIT 1"
    );

    $url = 'https://hub.pubcon.com.br';

    foreach ((array) $webhooks as $webhook) {
        $partes = wp_parse_url((string) $webhook->delivery_url);

        if (! empty($partes['host'])) {
            $url = ($partes['scheme'] ?? 'https').'://'.$partes['host'];
            break;
        }
    }

    $livros = post_type_exists('publicacao') ? (int) (wp_count_posts('publicacao')->publish ?? 0) : 0;
    $capitulos = post_type_exists('capitulo') ? (int) (wp_count_posts('capitulo')->publish ?? 0) : 0;

    $nao_registrados = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_conexao_doi_status'
         WHERE p.post_type = 'publicacao' AND p.post_status = 'publish' AND COALESCE(m.meta_value, 'none') <> 'registered'"
    );

    $ultimo_livro = $wpdb->get_var(
        "SELECT MAX(m.meta_value) FROM {$wpdb->postmeta} m
         JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_type = 'publicacao'
         WHERE m.meta_key = '_conexao_recebido_em'"
    );

    $dados = [
        'url' => (string) apply_filters('conexao_admin_url_hub', $url),
        'webhooks' => array_map(static fn ($w) => ['nome' => (string) $w->name, 'estado' => (string) $w->status, 'falhas' => (int) $w->failure_count], (array) $webhooks),
        'ultimo_acesso' => $chave ? (string) $chave : '',
        'livros' => $livros,
        'capitulos' => $capitulos,
        'nao_registrados' => $nao_registrados,
        'ultimo_livro' => $ultimo_livro ? (string) $ultimo_livro : '',
    ];

    set_transient('conexao_admin_hub', $dados, CONEXAO_ADMIN_CACHE);

    return $dados;
}

/** "há 2 min", "há 3 dias"; datas em horário do site. */
function conexao_admin_ha_quanto(string $data): string
{
    $momento = $data !== '' ? strtotime(get_gmt_from_date($data)) : false;

    if (! $momento) {
        return 'nunca';
    }

    return 'há '.human_time_diff($momento, time());
}

/** Contagens do "Agora". */
function conexao_admin_contagens(): array
{
    $conta = static fn (string $tipo): int => post_type_exists($tipo) ? (int) (wp_count_posts($tipo)->publish ?? 0) : 0;
    $usuarios = count_users();

    return [
        ['n' => $conta('product'), 'rotulo' => 'produtos', 'url' => 'edit.php?post_type=product'],
        ['n' => $conta('publicacao'), 'rotulo' => 'publicações DOI', 'url' => 'edit.php?post_type=publicacao'],
        ['n' => $conta('post'), 'rotulo' => 'posts', 'url' => 'edit.php'],
        ['n' => $conta('evento'), 'rotulo' => 'eventos', 'url' => 'edit.php?post_type=evento'],
        ['n' => (int) $usuarios['total_users'], 'rotulo' => sprintf('usuários (%d clientes)', (int) ($usuarios['avail_roles']['customer'] ?? 0)), 'url' => 'users.php'],
        ['n' => $conta('page'), 'rotulo' => 'páginas', 'url' => 'edit.php?post_type=page'],
    ];
}
