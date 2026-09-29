<?php
/**
 * Roda NO SITE NOVO: prepara a loja para o Hub Editora passar a ler daqui.
 *
 *  1. devolve aos pedidos migrados a data original de alteração, para a
 *     varredura do Hub não tratar o histórico inteiro como novidade;
 *  2. gera o mapa de ids (Pubcon → loja nova) de pedidos e produtos;
 *  3. cria a chave da API para o Hub (gravada em arquivo, nunca na tela);
 *  4. cria os avisos de pedido (webhooks) apontando para o Hub.
 *
 *   CONEXAO_HUB_URL=https://hub…  CONEXAO_HUB_SEGREDO=…  CONEXAO_SAIDA=~/hub-virada \
 *     wp eval-file preparar-hub.php
 */

global $wpdb;

$saida = rtrim(getenv('CONEXAO_SAIDA') ?: (getenv('HOME').'/hub-virada'), '/');
$hub = rtrim((string) getenv('CONEXAO_HUB_URL'), '/');
$segredo = (string) getenv('CONEXAO_HUB_SEGREDO');

if ($hub === '' || $segredo === '') {
    WP_CLI::error('Informe CONEXAO_HUB_URL e CONEXAO_HUB_SEGREDO.');
}

if (! is_dir($saida)) {
    mkdir($saida, 0700, true);
}

// ---- 1. datas de alteração dos pedidos migrados
$pedidos = $wpdb->prefix.'wc_orders';
$meta = $wpdb->prefix.'wc_orders_meta';
$operacional = $wpdb->prefix.'wc_order_operational_data';

$datas = $wpdb->query(
    "UPDATE {$pedidos} o
     JOIN {$meta} m ON m.order_id = o.id AND m.meta_key = '_pubcon_pedido'
     LEFT JOIN {$operacional} d ON d.order_id = o.id
     SET o.date_updated_gmt = COALESCE(d.date_completed_gmt, d.date_paid_gmt, o.date_created_gmt)
     WHERE o.date_updated_gmt > COALESCE(d.date_completed_gmt, d.date_paid_gmt, o.date_created_gmt)"
);

wp_cache_flush();
WP_CLI::log("datas de alteração devolvidas: {$datas} pedido(s)");

// ---- 2. mapa de ids
$mapa = ['orders' => [], 'products' => []];

foreach ($wpdb->get_results("SELECT order_id, meta_value FROM {$meta} WHERE meta_key = '_pubcon_pedido'") as $linha) {
    $mapa['orders'][(string) $linha->meta_value] = (string) $linha->order_id;
}

$formato = static function (WC_Product $livro, string $qual): int {
    foreach ($livro->get_children() as $filha) {
        if (get_post_meta($filha, 'attribute_pa_formato', true) === $qual && get_post_meta($filha, '_price', true) !== '') {
            return (int) $filha;
        }
    }

    return 0;
};

$sem_par = 0;

foreach ($wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_pubcon_id'") as $linha) {
    $local = (int) $linha->post_id;
    $base = (int) get_post_meta($local, '_conexao_unificado_em', true);
    $produto = wc_get_product($base ?: $local);

    if (! $produto) {
        $sem_par++;
        continue;
    }

    $novo = $produto->get_id();

    if ($produto->is_type('variable')) {
        // o produto antigo que foi absorvido por outro é o e-book; o que ficou é o impresso
        $novo = $base
            ? ($formato($produto, 'e-book') ?: $formato($produto, 'impresso'))
            : ($formato($produto, 'impresso') ?: $formato($produto, 'e-book'));
    }

    if (! $novo) {
        $sem_par++;
        continue;
    }

    $mapa['products'][(string) $linha->meta_value] = (string) $novo;
}

file_put_contents($saida.'/mapa.json', wp_json_encode($mapa));
chmod($saida.'/mapa.json', 0600);

WP_CLI::log(sprintf('mapa: %d pedidos, %d produtos (%d sem par)', count($mapa['orders']), count($mapa['products']), $sem_par));

// ---- 3. chave da API para o Hub
$descricao = 'Hub Editora';
$tabela = $wpdb->prefix.'woocommerce_api_keys';
$admin = get_users(['role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'fields' => 'ID']);

$wpdb->delete($tabela, ['description' => $descricao]);

$chave = 'ck_'.wc_rand_hash();
$senha = 'cs_'.wc_rand_hash();

$wpdb->insert($tabela, [
    'user_id' => (int) $admin[0],
    'description' => $descricao,
    'permissions' => 'read_write',
    'consumer_key' => wc_api_hash($chave),
    'consumer_secret' => $senha,
    'truncated_key' => substr($chave, -7),
]);

file_put_contents($saida.'/chave.env', "WOO_URL=".home_url()."\nWOO_CONSUMER_KEY={$chave}\nWOO_CONSUMER_SECRET={$senha}\n");
chmod($saida.'/chave.env', 0600);

WP_CLI::log('chave da API criada (leitura e escrita) e gravada em arquivo');

// ---- 4. avisos de pedido para o Hub
$destino = $hub.'/webhooks/woocommerce';
$existentes = [];

$loja = WC_Data_Store::load('webhook');

foreach ($loja->search_webhooks(['limit' => -1]) as $id) {
    $w = wc_get_webhook($id);

    if ($w && $w->get_delivery_url() === $destino) {
        $existentes[$w->get_topic()] = $w;
    }
}

foreach (['order.created' => 'Hub Editora: pedido criado', 'order.updated' => 'Hub Editora: pedido atualizado'] as $topico => $nome) {
    $w = $existentes[$topico] ?? new WC_Webhook();
    $w->set_name($nome);
    $w->set_user_id((int) $admin[0]);
    $w->set_topic($topico);
    $w->set_delivery_url($destino);
    $w->set_secret($segredo);
    $w->set_status('active');
    $w->set_api_version('wp_api_v3');
    $w->save();

    WP_CLI::log("aviso ativo: {$topico} → {$destino}");
}

WP_CLI::success('Loja pronta para o Hub.');
