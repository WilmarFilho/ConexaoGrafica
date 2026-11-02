<?php
/**
 * Página do produto na loja. Livro com publicação do Conexão DOI é redirecionado
 * para /livro/ pelo plugin; aqui chegam os demais, no mesmo layout (inc/livro.php).
 */

if (! defined('ABSPATH')) {
    exit;
}

the_post();

global $product;

if (! $product instanceof WC_Product) {
    $product = wc_get_product(get_the_ID());
}

conexao_pagina_livro($product, conexao_livro_publicacao_do_produto($product));
