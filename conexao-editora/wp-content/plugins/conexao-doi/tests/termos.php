<?php
/**
 * Confere os termos `autor` criados pelo PUT da fixture (wp eval-file).
 */

$esperados = [1 => 'Maria Exemplo da Silva', 2 => 'João Exemplo Pereira', 3 => 'Ana Exemplo Rocha', 4 => 'Carlos Exemplo Lima'];
$falhas = 0;

foreach ($esperados as $hub_id => $nome) {
    $termo = conexao_doi_termo_por_hub_id($hub_id);

    if (! $termo) {
        echo "  [ERRO] pessoa $hub_id ($nome) sem termo autor\n";
        $falhas++;
        continue;
    }

    $orcid = (string) get_term_meta($termo->term_id, '_conexao_orcid', true);
    $quantos = count(get_terms(['taxonomy' => 'autor', 'hide_empty' => false, 'name' => $nome, 'fields' => 'ids']));

    printf("  [%s] %s → termo %d (%s), orcid=%s, %d termo(s) com esse nome\n", $termo->name === $nome && $quantos === 1 ? 'ok' : 'ERRO', $nome, $termo->term_id, $termo->slug, $orcid ?: '-', $quantos);

    if ($termo->name !== $nome || $quantos !== 1) {
        $falhas++;
    }
}

$livro = conexao_doi_post_por_hub_id('publicacao', 1);
$termos = $livro ? wp_get_object_terms($livro->ID, 'autor', ['fields' => 'names']) : [];
printf("  [%s] livro ligado aos organizadores: %s\n", count($termos) === 2 ? 'ok' : 'ERRO', implode(', ', $termos));

exit($falhas ? 1 : 0);
