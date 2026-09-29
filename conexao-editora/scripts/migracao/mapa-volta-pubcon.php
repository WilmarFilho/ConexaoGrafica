<?php
/**
 * Roda NO SITE NOVO: mapa para o Hub voltar a ler a Pubcon enquanto a loja
 * nova não entra no ar (o inverso do preparar-hub.php).
 *
 *  - orders:   id do pedido aqui → id do pedido na Pubcon
 *  - products: id do produto/variação aqui → id do produto na Pubcon
 *  - refs:     id do produto na Pubcon → {id aqui, formato}, para o Hub
 *              reconhecer os produtos da Pubcon nos livros que já tem
 *
 *   CONEXAO_SAIDA=~/hub-volta.json wp eval-file mapa-volta-pubcon.php
 */

global $wpdb;

$saida = getenv('CONEXAO_SAIDA') ?: (getenv('HOME').'/hub-volta.json');
$mapa = ['orders' => [], 'products' => [], 'refs' => []];

foreach ($wpdb->get_results("SELECT order_id, meta_value FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key = '_pubcon_pedido'") as $linha) {
    $mapa['orders'][(string) $linha->order_id] = (string) $linha->meta_value;
}

$formato = static function (WC_Product $livro, string $qual): int {
    foreach ($livro->get_children() as $filha) {
        if (get_post_meta($filha, 'attribute_pa_formato', true) === $qual && get_post_meta($filha, '_price', true) !== '') {
            return (int) $filha;
        }
    }

    return 0;
};

// inclui os e-books antigos que estão na lixeira: é por eles que se sabe o id do e-book na Pubcon
foreach ($wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_pubcon_id'") as $linha) {
    $local = (int) $linha->post_id;
    $base = (int) get_post_meta($local, '_conexao_unificado_em', true);
    $produto = wc_get_product($base ?: $local);

    if (! $produto) {
        continue;
    }

    if ($produto->is_type('variable')) {
        $qual = $base ? 'e-book' : 'impresso';
        $novo = $formato($produto, $qual);
        $fmt = $base ? 'ebook' : 'fisico';
    } else {
        $novo = $produto->get_id();
        $fmt = ($produto->is_virtual() || $produto->is_downloadable()) ? 'ebook' : 'fisico';
    }

    if (! $novo) {
        continue;
    }

    $pubcon = (string) $linha->meta_value;
    $mapa['products'][(string) $novo] = $pubcon;
    $mapa['refs'][$pubcon] = ['novo' => (string) $novo, 'formato' => $fmt];
}

file_put_contents($saida, wp_json_encode($mapa));
chmod($saida, 0600);

WP_CLI::success(sprintf('%d pedidos, %d produtos → %s', count($mapa['orders']), count($mapa['refs']), $saida));
