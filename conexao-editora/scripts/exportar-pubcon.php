<?php
/**
 * Varredura do catálogo da loja atual (novo.pubcon.com.br) pela API pública do
 * WooCommerce, gravando scripts/dados/pubcon-produtos.json no formato que o
 * importar-pubcon.php espera.
 *
 *   wp eval-file /scripts/exportar-pubcon.php
 *
 * A API pública não expõe peso e dimensões; quem já tem esses dados aqui não
 * os perde, porque o importador só grava o que vem preenchido.
 */

$base = getenv('CONEXAO_LOJA') ?: 'https://novo.pubcon.com.br';
$destino = file_exists('/scripts/dados') ? '/scripts/dados/pubcon-produtos.json' : __DIR__.'/dados/pubcon-produtos.json';

$produtos = [];
$pagina = 1;

do {
    $url = $base.'/wp-json/wc/store/v1/products?per_page=50&page='.$pagina;
    $resposta = wp_remote_get($url, ['timeout' => 45]);

    if (is_wp_error($resposta)) {
        WP_CLI::error($resposta->get_error_message());
    }

    $lote = json_decode((string) wp_remote_retrieve_body($resposta), true);

    if (! is_array($lote) || ! $lote) {
        break;
    }

    foreach ($lote as $p) {
        $precos = $p['prices'] ?? [];
        $casas = (int) ($precos['currency_minor_unit'] ?? 2);

        $valor = static function ($bruto) use ($casas) {
            if ($bruto === null || $bruto === '') {
                return '';
            }

            return number_format(((float) $bruto) / (10 ** $casas), 2, '.', '');
        };

        $imagens = array_values(array_filter(array_map(
            static fn ($img) => $img['src'] ?? '',
            $p['images'] ?? []
        )));

        $regular = $valor($precos['regular_price'] ?? '');
        $promocao = $valor($precos['sale_price'] ?? '');

        $produtos[] = [
            'id' => (int) $p['id'],
            'tipo' => (string) ($p['type'] ?? 'simple'),
            // a API devolve o nome com entidades (&#8211;); aqui volta a ser texto
            'nome' => html_entity_decode(wp_strip_all_tags((string) ($p['name'] ?? '')), ENT_QUOTES, 'UTF-8'),
            'slug' => (string) ($p['slug'] ?? ''),
            'status' => 'publish',
            'sku' => (string) ($p['sku'] ?? ''),
            'preco' => $regular,
            'promocao' => ($promocao !== '' && $promocao !== $regular) ? $promocao : '',
            'descricao' => (string) ($p['description'] ?? ''),
            'resumo' => (string) ($p['short_description'] ?? ''),
            'peso' => '',
            'dimensoes' => ['comprimento' => '', 'largura' => '', 'altura' => ''],
            'estoque' => ! empty($p['is_in_stock']) ? 'instock' : 'outofstock',
            'quantidade' => $p['low_stock_remaining'] ?? null,
            'virtual' => ! empty($p['is_virtual']),
            'baixavel' => ! empty($p['is_downloadable']),
            'categorias' => array_values(array_map(static fn ($c) => (string) $c['name'], $p['categories'] ?? [])),
            'tags' => array_values(array_map(static fn ($t) => (string) $t['name'], $p['tags'] ?? [])),
            'capa' => $imagens[0] ?? '',
            'galeria' => array_slice($imagens, 1),
            'data' => '',
            'atributos' => [],
        ];
    }

    $pagina++;
} while (count($lote) === 50);

file_put_contents($destino, wp_json_encode($produtos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

WP_CLI::success(sprintf('%d produtos gravados em %s', count($produtos), $destino));
