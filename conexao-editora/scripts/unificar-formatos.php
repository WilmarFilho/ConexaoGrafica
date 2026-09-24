<?php
/**
 * Junta o livro impresso e o e-book do mesmo título num produto só, com o
 * atributo Formato (Impresso / E-book / Impresso + E-book).
 *
 *   wp eval-file /scripts/unificar-formatos.php          (simulação)
 *   CONEXAO_APLICAR=1 wp eval-file /scripts/unificar-formatos.php
 *
 * O impresso é quem vira o produto único: ele mantém endereço, avaliações e
 * histórico. O e-book antigo vira rascunho e passa a redirecionar para ele.
 * A variação "Impresso + E-book" nasce sem preço — o WooCommerce só a mostra
 * quando a editora definir o valor.
 */

if (! class_exists('WooCommerce')) {
    WP_CLI::error('WooCommerce não está ativo.');
}

$aplicar = (bool) getenv('CONEXAO_APLICAR');

$formatos = [
    'impresso' => 'Impresso',
    'e-book' => 'E-book',
    'impresso-e-book' => 'Impresso + E-book',
];

/** Cria (uma vez) o atributo global Formato e os seus termos. */
$garante_atributo = static function () use ($formatos): string {
    $taxonomia = 'pa_formato';

    if (! taxonomy_exists($taxonomia)) {
        $id = wc_create_attribute([
            'name' => 'Formato',
            'slug' => 'formato',
            'type' => 'select',
            'order_by' => 'menu_order',
            'has_archives' => false,
        ]);

        if (is_wp_error($id)) {
            WP_CLI::error($id->get_error_message());
        }

        register_taxonomy($taxonomia, ['product'], ['hierarchical' => false, 'show_ui' => false, 'query_var' => true, 'rewrite' => false]);
    }

    foreach ($formatos as $slug => $nome) {
        if (! term_exists($slug, $taxonomia)) {
            wp_insert_term($nome, $taxonomia, ['slug' => $slug]);
        }
    }

    return $taxonomia;
};

/** Título sem o prefixo de formato, sem acento e sem pontuação. */
$chave = static function (string $titulo): string {
    $titulo = preg_replace('/^\s*e-?books?\s*[:\-–]?\s*/iu', '', $titulo);
    $titulo = remove_accents($titulo);
    $titulo = preg_replace('/[^a-z0-9]+/i', ' ', $titulo);

    return trim(strtolower($titulo));
};

$ehEbook = static fn (string $titulo): bool => (bool) preg_match('/^\s*e-?book/iu', $titulo);

$todos = wc_get_products(['status' => ['publish', 'draft'], 'limit' => -1]);
$impressos = [];
$ebooks = [];

foreach ($todos as $produto) {
    if ($produto->get_type() === 'variable') {
        continue; // já unificado
    }

    if ($ehEbook($produto->get_name())) {
        $ebooks[] = $produto;
        continue;
    }

    $impressos[$chave($produto->get_name())][] = $produto;
}

$pares = [];
$sozinhos = [];

foreach ($ebooks as $ebook) {
    $k = $chave($ebook->get_name());

    if (! empty($impressos[$k])) {
        $pares[] = ['impresso' => $impressos[$k][0], 'ebook' => $ebook];
        continue;
    }

    $sozinhos[] = $ebook;
}

WP_CLI::log(sprintf(
    '%s: %d pares, %d e-books sem par, %d impressos sem e-book',
    $aplicar ? 'Aplicando' : 'Simulação',
    count($pares),
    count($sozinhos),
    count($impressos) - count($pares)
));

if (! $aplicar) {
    foreach ($pares as $par) {
        WP_CLI::log(sprintf(
            '  %s  (impresso #%d %s + e-book #%d %s)',
            $par['impresso']->get_name(),
            $par['impresso']->get_id(),
            wc_price($par['impresso']->get_price()) ? strip_tags(wc_price($par['impresso']->get_price())) : 's/ preço',
            $par['ebook']->get_id(),
            $par['ebook']->get_price() ? strip_tags(wc_price($par['ebook']->get_price())) : 's/ preço'
        ));
    }

    WP_CLI::success('Nada foi alterado. Rode com CONEXAO_APLICAR=1 para valer.');

    return;
}

$taxonomia = $garante_atributo();
$termos = [];

foreach (array_keys($formatos) as $slug) {
    $termo = get_term_by('slug', $slug, $taxonomia);
    $termos[$slug] = $termo ? (int) $termo->term_id : 0;
}

$feitos = 0;

foreach ($pares as $par) {
    /** @var WC_Product $impresso */
    $impresso = $par['impresso'];
    /** @var WC_Product $ebook */
    $ebook = $par['ebook'];

    $dadosImpresso = [
        'preco' => $impresso->get_regular_price(),
        'promocao' => $impresso->get_sale_price(),
        'peso' => $impresso->get_weight(),
        'comprimento' => $impresso->get_length(),
        'largura' => $impresso->get_width(),
        'altura' => $impresso->get_height(),
        'estoque' => $impresso->get_stock_status(),
    ];

    $variavel = new WC_Product_Variable($impresso->get_id());

    $atributo = new WC_Product_Attribute();
    $atributo->set_id(wc_attribute_taxonomy_id_by_name('formato'));
    $atributo->set_name($taxonomia);
    $atributo->set_options(array_values($termos));
    $atributo->set_position(0);
    $atributo->set_visible(true);
    $atributo->set_variation(true);

    $variavel->set_attributes([$taxonomia => $atributo]);
    $variavel->set_regular_price('');
    $variavel->set_sale_price('');
    $variavel->save();

    $criaVariacao = static function (string $slug, array $dados) use ($variavel, $taxonomia): void {
        $variacao = new WC_Product_Variation();
        $variacao->set_parent_id($variavel->get_id());
        $variacao->set_attributes([$taxonomia => $slug]);
        $variacao->set_status('publish');

        // preço vazio ou zero: a variação nasce sem preço e o WooCommerce a
        // esconde da loja até a editora definir o valor
        if ((float) $dados['preco'] > 0) {
            $variacao->set_regular_price((string) $dados['preco']);
        }

        if (! empty($dados['promocao'])) {
            $variacao->set_sale_price((string) $dados['promocao']);
        }

        $variacao->set_virtual((bool) ($dados['virtual'] ?? false));
        $variacao->set_downloadable((bool) ($dados['baixavel'] ?? false));

        if (! empty($dados['peso'])) {
            $variacao->set_weight((string) $dados['peso']);
        }

        if (! empty($dados['comprimento'])) {
            $variacao->set_length((string) $dados['comprimento']);
            $variacao->set_width((string) ($dados['largura'] ?? ''));
            $variacao->set_height((string) ($dados['altura'] ?? ''));
        }

        if (! empty($dados['estoque'])) {
            $variacao->set_stock_status((string) $dados['estoque']);
        }

        $variacao->save();
    };

    $criaVariacao('impresso', $dadosImpresso + ['virtual' => false, 'baixavel' => false]);

    $criaVariacao('e-book', [
        'preco' => $ebook->get_regular_price(),
        'promocao' => $ebook->get_sale_price(),
        'virtual' => true,
        'baixavel' => true,
        'estoque' => 'instock',
    ]);

    // o combo nasce sem preço: o WooCommerce só o mostra depois que a editora
    // definir quanto custa
    $criaVariacao('impresso-e-book', [
        'preco' => '',
        'promocao' => '',
        'virtual' => false,
        'baixavel' => true,
        'peso' => $dadosImpresso['peso'],
        'comprimento' => $dadosImpresso['comprimento'],
        'largura' => $dadosImpresso['largura'],
        'altura' => $dadosImpresso['altura'],
        'estoque' => $dadosImpresso['estoque'],
    ]);

    WC_Product_Variable::sync($variavel->get_id());

    // o e-book antigo sai do catálogo e aponta para o produto unificado
    $ebook->set_status('draft');
    $ebook->set_catalog_visibility('hidden');
    $ebook->save();
    update_post_meta($ebook->get_id(), '_conexao_unificado_em', $variavel->get_id());

    $feitos++;
    WP_CLI::log(sprintf('  + %s (#%d) ← e-book #%d', $variavel->get_name(), $variavel->get_id(), $ebook->get_id()));
}

wc_delete_product_transients();

WP_CLI::success(sprintf('%d títulos unificados.', $feitos));
