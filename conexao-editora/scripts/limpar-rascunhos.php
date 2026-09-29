<?php
/**
 * Manda para a lixeira os produtos em rascunho da loja (os e-books antigos que
 * viraram formato de outro livro, e títulos fora de venda). Antes, guarda:
 *  - o endereço de cada e-book antigo → livro unificado, para o redirecionamento
 *    continuar (opção conexao_enderecos_antigos);
 *  - um registro do que saiu, com o código da Pubcon (opção conexao_rascunhos_removidos).
 *
 *   wp eval-file limpar-rascunhos.php                     simulação
 *   CONEXAO_APLICAR=1 wp eval-file limpar-rascunhos.php   para valer
 */

$aplicar = (bool) getenv('CONEXAO_APLICAR');
$ids = get_posts(['post_type' => 'product', 'post_status' => 'draft', 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC']);

$mapa = (array) get_option('conexao_enderecos_antigos', []);
$registro = (array) get_option('conexao_rascunhos_removidos', []);
$feitos = 0;

foreach ($ids as $id) {
    $slug = get_post_field('post_name', $id);
    $destino = (int) get_post_meta($id, '_conexao_unificado_em', true);

    if ($destino && get_post_status($destino) !== 'publish') {
        WP_CLI::warning("#{$id} aponta para #{$destino}, que não está publicado: fica.");
        continue;
    }

    WP_CLI::log(sprintf('#%d %s%s', $id, html_entity_decode(get_the_title($id)), $destino ? " → #{$destino}" : ''));

    if (! $aplicar) {
        $feitos++;
        continue;
    }

    if ($destino && $slug) {
        $mapa[$slug] = $destino;
    }

    $registro[$id] = [
        'titulo' => html_entity_decode(get_the_title($id)),
        'slug' => $slug,
        'pubcon' => (string) get_post_meta($id, '_pubcon_id', true),
        'destino' => $destino,
        'quando' => current_time('mysql'),
    ];

    update_option('conexao_enderecos_antigos', $mapa, false);
    update_option('conexao_rascunhos_removidos', $registro, false);

    if (wp_trash_post($id)) {
        $feitos++;
    }
}

WP_CLI::success(sprintf('%s: %d produto(s) %s.', $aplicar ? 'Feito' : 'Simulação', $feitos, $aplicar ? 'na lixeira' : 'iriam para a lixeira'));
