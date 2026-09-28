<?php
/**
 * Roda NA LOJA ANTIGA (Pubcon), só lendo: a ficha completa de cada produto
 * (campos do livro, peso e medidas, etiquetas, capa e páginas de amostra).
 *
 *   CONEXAO_PACOTE=~/pubcon-produtos.json wp eval-file exportar-produtos.php
 */

global $wpdb;

$destino = getenv('CONEXAO_PACOTE') ?: (getenv('HOME').'/pubcon-produtos.json');
$envios = wp_get_upload_dir();

$arquivo = static function ($item) use ($envios): ?array {
    $url = is_numeric($item) ? (string) wp_get_attachment_url((int) $item) : trim((string) $item);

    if ($url === '' || strpos($url, $envios['baseurl']) !== 0) {
        return null;
    }

    $relativo = ltrim(substr($url, strlen($envios['baseurl'])), '/');

    return ['url' => $url, 'relativo' => $relativo, 'existe' => file_exists($envios['basedir'].'/'.$relativo)];
};

$produtos = [];

foreach ($wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status IN ('publish','draft','private') ORDER BY ID") as $id) {
    $p = wc_get_product($id);

    if (! $p) {
        continue;
    }

    $fotos = get_post_meta($id, 'fotos', true);
    $fotos = is_array($fotos) ? $fotos : array_filter(array_map('trim', explode(',', (string) $fotos)));
    $amostras = [];

    foreach ($fotos as $foto) {
        $foto = is_array($foto) ? ($foto['url'] ?? ($foto['id'] ?? '')) : $foto;
        $dados = $arquivo($foto);

        if ($dados) {
            $amostras[] = $dados;
        }
    }

    $campo = static fn (string $chave): string => trim((string) get_post_meta($id, $chave, true));

    $produtos[] = [
        'id' => (int) $id,
        'nome' => html_entity_decode(get_the_title($id), ENT_QUOTES, 'UTF-8'),
        'virtual' => $p->is_virtual(),
        'sku' => $p->get_sku(),
        'preco' => $p->get_regular_price(),
        'promocao' => $p->get_sale_price(),
        'estoque' => $p->get_stock_status(),
        'peso' => $p->get_weight(),
        'medidas' => ['length' => $p->get_length(), 'width' => $p->get_width(), 'height' => $p->get_height()],
        'resumo' => $p->get_short_description(),
        'descricao' => $p->get_description(),
        'autores' => $campo('autor') ?: $campo('writen_by'),
        'editora' => $campo('editora') ?: $campo('publisher'),
        'ano' => $campo('data-da-publicacao') ?: $campo('year'),
        'isbn' => $campo('data-da-publicacao_copy'),
        'idioma' => $campo('idioma'),
        'paginas' => $campo('numero-de-paginas'),
        'google_play' => $campo('google-play'),
        'etiquetas' => wp_get_post_terms($id, 'product_tag', ['fields' => 'names']),
        'categorias' => wp_get_post_terms($id, 'product_cat', ['fields' => 'names']),
        'capa' => $arquivo(get_post_thumbnail_id($id)),
        'amostras' => $amostras,
    ];
}

file_put_contents($destino, wp_json_encode(['origem' => home_url(), 'produtos' => $produtos], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$arquivos = [];

foreach ($produtos as $p) {
    foreach (array_merge([$p['capa']], $p['amostras']) as $a) {
        if ($a && $a['existe']) {
            $arquivos[$a['relativo']] = true;
        }
    }
}

file_put_contents($destino.'.arquivos', implode("\n", array_keys($arquivos))."\n");

WP_CLI::success(sprintf('%d produtos, %d imagens (capas e amostras) → %s', count($produtos), count($arquivos), $destino));
