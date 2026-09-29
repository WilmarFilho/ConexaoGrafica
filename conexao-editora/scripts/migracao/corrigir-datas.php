<?php
/**
 * Roda NO SITE NOVO: devolve aos pedidos migrados as datas que tinham na
 * Pubcon. Ao marcar um pedido como concluído, o WooCommerce grava a data do
 * momento — na importação, isso trocou a conclusão de pedidos antigos pela
 * data da carga.
 *
 * Entrada (CONEXAO_DATAS): TSV gerado na Pubcon, uma linha por pedido:
 *   id <TAB> conclusão (timestamp ou vazio) <TAB> pagamento (timestamp ou vazio) <TAB> alteração (GMT)
 *
 *   CONEXAO_DATAS=~/pubcon-datas.tsv wp eval-file corrigir-datas.php
 */

global $wpdb;

$arquivo = getenv('CONEXAO_DATAS') ?: (getenv('HOME').'/pubcon-datas.tsv');

if (! file_exists($arquivo)) {
    WP_CLI::error("não achei {$arquivo}");
}

$pedidos = $wpdb->prefix.'wc_orders';
$meta = $wpdb->prefix.'wc_orders_meta';
$operacional = $wpdb->prefix.'wc_order_operational_data';
$estatisticas = $wpdb->prefix.'wc_order_stats';

$locais = $wpdb->get_results("SELECT meta_value AS origem, order_id FROM {$meta} WHERE meta_key = '_pubcon_pedido'", OBJECT_K);
$gmt = static fn (string $ts): ?string => ctype_digit($ts) && (int) $ts > 0 ? gmdate('Y-m-d H:i:s', (int) $ts) : null;
$acertados = 0;

foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
    [$origem, $conclusao, $pagamento, $alteracao] = array_pad(explode("\t", $linha), 4, '');

    if (! isset($locais[$origem])) {
        continue;
    }

    $id = (int) $locais[$origem]->order_id;
    $concluido = $gmt(trim($conclusao) === 'NULL' ? '' : trim($conclusao));
    $pago = $gmt(trim($pagamento) === 'NULL' ? '' : trim($pagamento));
    $alterado = preg_match('/^\d{4}-\d{2}-\d{2} /', $alteracao) ? $alteracao : null;

    $wpdb->update($operacional, ['date_completed_gmt' => $concluido, 'date_paid_gmt' => $pago], ['order_id' => $id]);

    if ($alterado) {
        $wpdb->update($pedidos, ['date_updated_gmt' => $alterado], ['id' => $id]);
    }

    $wpdb->update($estatisticas, [
        'date_completed' => $concluido ? get_date_from_gmt($concluido) : null,
        'date_paid' => $pago ? get_date_from_gmt($pago) : null,
    ], ['order_id' => $id]);

    $acertados++;
}

wp_cache_flush();

$recentes = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$pedidos} o JOIN {$meta} m ON m.order_id = o.id AND m.meta_key = '_pubcon_pedido'
     WHERE o.date_updated_gmt > (UTC_TIMESTAMP() - INTERVAL 3 DAY)"
);

WP_CLI::success("{$acertados} pedidos com as datas originais. Alterados nos últimos 3 dias: {$recentes}.");
