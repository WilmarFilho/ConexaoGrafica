<?php
/**
 * Os e-books que não têm versão impressa ganham a página padrão do livro:
 * título sem "E-book" e o atributo Formato, com só o E-book à venda. O
 * Impresso e o combo nascem sem preço e fora de estoque (aparecem apagados) e
 * entram à venda quando a editora cadastrar preço e estoque do impresso.
 *
 *   wp eval-file formatar-ebooks-avulsos.php          (simulação)
 *   CONEXAO_APLICAR=1 wp eval-file formatar-ebooks-avulsos.php
 *   CONEXAO_PULAR=135,137 ...                          (ids que ficam de fora)
 *
 * A simulação grava em CONEXAO_RETRATO (padrão: ~/ebooks-avulsos-antes.json)
 * os livros que cada comprador lê hoje; a aplicação exige esse arquivo e
 * compara com o resultado depois da troca.
 *
 * O produto continua com o mesmo id (endereço antigo redireciona, avaliações e
 * histórico ficam). Os pedidos antigos passam a apontar para a variação
 * E-book, como a importação fez com os unificados: sem isso a biblioteca
 * deixaria de liberar o livro para quem já comprou. A biblioteca de cada
 * comprador é conferida antes e depois.
 */

if (! class_exists('WooCommerce')) {
    WP_CLI::error('WooCommerce não está ativo.');
}

global $wpdb;

$aplicar = (bool) getenv('CONEXAO_APLICAR');
$arquivoRetrato = getenv('CONEXAO_RETRATO') ?: (getenv('HOME').'/ebooks-avulsos-antes.json');
$pular = array_filter(array_map('intval', explode(',', (string) getenv('CONEXAO_PULAR'))));
$taxonomia = 'pa_formato';

if (! taxonomy_exists($taxonomia)) {
    WP_CLI::error('Atributo Formato não existe. Rode antes o unificar-formatos.php.');
}

$termos = [];

foreach (['impresso', 'e-book', 'impresso-e-book'] as $slug) {
    $termo = get_term_by('slug', $slug, $taxonomia);

    if (! $termo) {
        WP_CLI::error("Termo {$slug} do Formato não existe.");
    }

    $termos[$slug] = (int) $termo->term_id;
}

$semPrefixo = static fn (string $titulo): string => trim(preg_replace('/^\s*e-?books?\s*[:\-–]?\s*/iu', '', $titulo));

/** Itens de pedido que apontam para o produto sem variação. */
$itensDe = static function (int $produto_id) use ($wpdb): array {
    return array_map('intval', $wpdb->get_col($wpdb->prepare(
        "SELECT i.order_item_id
           FROM {$wpdb->prefix}woocommerce_order_items i
           JOIN {$wpdb->prefix}woocommerce_order_itemmeta p ON p.order_item_id = i.order_item_id AND p.meta_key = '_product_id' AND p.meta_value = %d
           LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta v ON v.order_item_id = i.order_item_id AND v.meta_key = '_variation_id'
          WHERE i.order_item_type = 'line_item' AND (v.meta_value IS NULL OR v.meta_value = '' OR v.meta_value = '0')",
        $produto_id
    )));
};

/** Compradores dos produtos e o que cada um lê hoje (biblioteca). */
$compradores = static function (array $ids) use ($wpdb): array {
    if (! $ids) {
        return [];
    }

    $lista = implode(',', array_map('intval', $ids));

    return array_map('intval', $wpdb->get_col(
        "SELECT DISTINCT o.customer_id
           FROM {$wpdb->prefix}wc_order_product_lookup l
           JOIN {$wpdb->prefix}wc_orders o ON o.id = l.order_id
          WHERE l.product_id IN ({$lista}) AND o.customer_id > 0"
    ));
};

$retrato = static function (array $usuarios): array {
    $mapa = [];

    foreach ($usuarios as $usuario) {
        $livros = array_keys(conexao_biblioteca_livros_do_usuario($usuario));
        sort($livros);
        $mapa[$usuario] = $livros;
    }

    return $mapa;
};

// ---- candidatos: produto simples, publicado, com título começando por e-book
$candidatos = [];

foreach (wc_get_products(['status' => ['publish', 'private'], 'type' => 'simple', 'limit' => -1]) as $produto) {
    if (! preg_match('/^\s*e-?book/iu', $produto->get_name()) || in_array($produto->get_id(), $pular, true)) {
        continue;
    }

    $candidatos[] = $produto;
}

// um título igual (sem o prefixo) já publicado indica par que deveria ser unificado
$titulos = [];

foreach (wc_get_products(['status' => ['publish', 'private'], 'limit' => -1]) as $produto) {
    $titulos[strtolower(remove_accents($produto->get_name()))] = $produto->get_id();
}

WP_CLI::log(sprintf('%s: %d e-books avulsos', $aplicar ? 'Aplicando' : 'Simulação', count($candidatos)));

$ids = [];

foreach ($candidatos as $produto) {
    $novo = $semPrefixo($produto->get_name());
    $colide = $titulos[strtolower(remove_accents($novo))] ?? 0;
    $ids[] = $produto->get_id();

    WP_CLI::log(sprintf(
        '  #%d  %s  →  %s  | E-book %s | %d itens de pedido%s',
        $produto->get_id(),
        $produto->get_name(),
        $novo,
        $produto->get_regular_price() === '' ? 's/ preço' : 'R$ '.$produto->get_regular_price(),
        count($itensDe($produto->get_id())),
        $colide ? sprintf('  !! já existe produto #%d com este título', $colide) : ''
    ));
}

if (! $aplicar) {
    // a biblioteca guarda o resultado na memória do processo: o retrato de
    // antes sai da simulação, e a aplicação só consulta depois da troca
    $usuarios = $compradores($ids);
    file_put_contents($arquivoRetrato, wp_json_encode($retrato($usuarios)));
    WP_CLI::log(sprintf('compradores com conta: %d (retrato em %s)', count($usuarios), $arquivoRetrato));
    WP_CLI::success('Nada foi alterado. Rode com CONEXAO_APLICAR=1 para valer.');

    return;
}

$antes = is_readable($arquivoRetrato) ? json_decode((string) file_get_contents($arquivoRetrato), true) : null;

if (! is_array($antes)) {
    WP_CLI::error("Retrato da biblioteca não encontrado em {$arquivoRetrato}. Rode a simulação antes.");
}

$feitos = 0;

foreach ($candidatos as $simples) {
    $id = $simples->get_id();
    $preco = $simples->get_regular_price();
    $promocao = $simples->get_sale_price();
    $itens = $itensDe($id);

    $variavel = new WC_Product_Variable($id);

    $atributo = new WC_Product_Attribute();
    $atributo->set_id(wc_attribute_taxonomy_id_by_name('formato'));
    $atributo->set_name($taxonomia);
    $atributo->set_options(array_values($termos));
    $atributo->set_position(0);
    $atributo->set_visible(true);
    $atributo->set_variation(true);

    $variavel->set_name($semPrefixo($simples->get_name()));
    $variavel->set_slug(''); // o WordPress gera o novo e guarda o antigo para redirecionar
    $variavel->set_attributes([$taxonomia => $atributo]);
    $variavel->set_regular_price('');
    $variavel->set_sale_price('');
    $variavel->set_virtual(false);
    $variavel->set_downloadable(false);
    $variavel->set_manage_stock(false);
    $variavel->set_stock_status('instock');
    $variavel->save();

    wp_set_object_terms($id, 'variable', 'product_type');

    $criaVariacao = static function (string $slug, array $dados) use ($id, $taxonomia): int {
        $variacao = new WC_Product_Variation();
        $variacao->set_parent_id($id);
        $variacao->set_attributes([$taxonomia => $slug]);
        $variacao->set_status('publish');
        $variacao->set_regular_price($dados['preco']);
        $variacao->set_sale_price($dados['promocao'] ?? '');
        $variacao->set_virtual($dados['virtual']);
        $variacao->set_downloadable($dados['baixavel']);
        $variacao->set_stock_status($dados['estoque']);

        return (int) $variacao->save();
    };

    // impresso e combo sem preço e fora de estoque: aparecem apagados até a
    // editora cadastrar o livro físico
    $criaVariacao('impresso', ['preco' => '', 'virtual' => false, 'baixavel' => false, 'estoque' => 'outofstock']);

    // e-book gratuito continua gratuito: preço 0 é preço, e a variação aparece
    $ebook = $criaVariacao('e-book', [
        'preco' => $preco === '' ? '0' : (string) $preco,
        'promocao' => (string) $promocao,
        'virtual' => true,
        'baixavel' => true,
        'estoque' => 'instock',
    ]);

    $criaVariacao('impresso-e-book', ['preco' => '', 'virtual' => false, 'baixavel' => true, 'estoque' => 'outofstock']);

    WC_Product_Variable::sync($id);
    wc_delete_product_transients($id);

    foreach ($itens as $item) {
        wc_update_order_item_meta($item, '_variation_id', $ebook);
        wc_update_order_item_meta($item, 'pa_formato', 'e-book');
    }

    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}wc_order_product_lookup SET variation_id = %d WHERE product_id = %d AND variation_id = 0",
        $ebook,
        $id
    ));

    $feitos++;
    WP_CLI::log(sprintf('  + #%d %s (e-book #%d, %d itens de pedido)', $id, get_the_title($id), $ebook, count($itens)));
}

wc_delete_product_transients();

$depois = $retrato(array_map('intval', array_keys($antes)));

$perdas = 0;

foreach ($antes as $usuario => $livros) {
    $agora = $depois[$usuario] ?? null;

    if ($agora === null) {
        WP_CLI::warning("não consegui conferir o usuário {$usuario}");
        $perdas++;
        continue;
    }

    $faltam = array_diff($livros, $agora);

    if ($faltam) {
        WP_CLI::warning(sprintf('usuário %d perdeu acesso a: %s', $usuario, implode(', ', $faltam)));
        $perdas++;
    }
}

if ($perdas) {
    WP_CLI::error(sprintf('%d livros convertidos, mas %d compradores com diferença na biblioteca. Restaure o backup.', $feitos, $perdas));
}

WP_CLI::success(sprintf('%d livros convertidos; a biblioteca dos %d compradores ficou igual.', $feitos, count($antes)));
