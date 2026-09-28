<?php
/**
 * Herança da loja antiga (Pubcon): o pedido migrado continua com o número que
 * o cliente recebeu por e-mail, e o painel o encontra por esse número.
 */

if (! defined('ABSPATH')) {
    exit;
}

add_filter('woocommerce_order_number', function ($numero, $pedido) {
    $antigo = $pedido instanceof WC_Abstract_Order ? (string) $pedido->get_meta('_pubcon_numero') : '';

    return $antigo !== '' ? $antigo : $numero;
}, 10, 2);

add_filter('woocommerce_order_table_search_query_meta_keys', function (array $chaves): array {
    $chaves[] = '_pubcon_numero';

    return $chaves;
});

add_filter('woocommerce_shop_order_search_fields', function (array $campos): array {
    $campos[] = '_pubcon_numero';

    return $campos;
});
