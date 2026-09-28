<?php
/**
 * Depois da importação: faz os pedidos novos continuarem a numeração da loja
 * antiga, para um número novo nunca repetir o de um pedido migrado.
 *
 *   wp eval-file numeracao.php
 */

global $wpdb;

$tabela = $wpdb->prefix.'wc_orders_meta';
$maior_antigo = (int) $wpdb->get_var("SELECT MAX(CAST(meta_value AS UNSIGNED)) FROM {$tabela} WHERE meta_key = '_pubcon_numero'");
$maior_atual = (int) $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts}");

if (! $maior_antigo) {
    WP_CLI::error('Nenhum pedido migrado encontrado.');
}

$proximo = (int) (ceil(($maior_antigo + 1) / 1000) * 1000);

if ($maior_atual >= $proximo) {
    WP_CLI::success("Nada a fazer: os IDs já passaram de {$maior_antigo} (maior atual: {$maior_atual}).");

    return;
}

$wpdb->query("ALTER TABLE {$wpdb->posts} AUTO_INCREMENT = {$proximo}");

WP_CLI::success("Último número antigo: {$maior_antigo}. Próximo pedido sai a partir de {$proximo}.");
