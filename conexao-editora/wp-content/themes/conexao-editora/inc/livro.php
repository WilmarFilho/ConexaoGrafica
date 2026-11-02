<?php
/**
 * Página do livro: uma só para todo livro, com ou sem DOI (Figma "Site - Conexão
 * Editora", quadro 2197:568). Junta o produto da loja (preço, formatos, frete,
 * avaliações, amostras) e, quando existe, a publicação do Conexão DOI (DOI,
 * sumário, capítulos, autores, referências, licenças, citação e ficha em PDF).
 *
 * A página de produto (sem publicação) e a página /livro/ do plugin conexao-doi
 * chamam o mesmo template: template-parts/livro/pagina.php.
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Ícone da pasta assets/img/produto no tamanho e nas cores do arquivo (os do
 * Figma já vêm com a medida certa; nada de redimensionar).
 */
function conexao_livro_svg(string $nome, string $classe = ''): string
{
    $caminho = get_template_directory().'/assets/img/produto/'.sanitize_file_name($nome).'.svg';

    if (! file_exists($caminho)) {
        return '';
    }

    $svg = (string) file_get_contents($caminho);
    // o export do Figma traz style="display: block" e preserveAspectRatio="none"; o layout cuida disso
    $svg = preg_replace('/\s(?:style|preserveAspectRatio|overflow)="[^"]*"/i', '', $svg, 3);

    return str_replace(
        '<svg ',
        sprintf('<svg class="livro-icone livro-icone--%s%s" aria-hidden="true" focusable="false" ', esc_attr($nome), $classe !== '' ? ' '.esc_attr($classe) : ''),
        $svg
    );
}

/** Há publicação do Conexão DOI ligada a este produto? */
function conexao_livro_publicacao_do_produto(?WC_Product $produto): int
{
    if (! $produto || ! function_exists('conexao_doi_publicacao_do_produto')) {
        return 0;
    }

    return (int) conexao_doi_publicacao_do_produto($produto->get_id());
}

/** Produto da loja ligado à publicação (o primeiro link de compra com produto). */
function conexao_livro_produto_da_publicacao(int $publicacao_id): ?WC_Product
{
    if ($publicacao_id < 1 || ! function_exists('conexao_doi_ofertas')) {
        return null;
    }

    foreach (conexao_doi_ofertas($publicacao_id) as $oferta) {
        $produto = $oferta['pai'] ?? null ?: ($oferta['produto'] ?? null);

        if ($produto instanceof WC_Product) {
            return $produto->is_type('variation') ? wc_get_product($produto->get_parent_id()) : $produto;
        }
    }

    return null;
}

/**
 * Tudo o que a página mostra, já resolvido entre produto e publicação. Sem
 * publicação, os blocos de DOI, sumário, capítulos, referências e licença somem.
 *
 * @return array<string, mixed>
 */
function conexao_livro_contexto(?WC_Product $produto, int $publicacao_id = 0): array
{
    $doi_ativo = $publicacao_id > 0 && function_exists('conexao_doi_dados_livro');
    $dados = $doi_ativo ? conexao_doi_dados_livro($publicacao_id) : ['book' => [], 'chapters' => []];
    $livro = (array) ($dados['book'] ?? []);

    $titulo = $doi_ativo && $livro ? conexao_doi_titulo_completo($livro) : ($produto ? $produto->get_name() : get_the_title());
    $url = $doi_ativo ? (string) get_permalink($publicacao_id) : ($produto ? (string) $produto->get_permalink() : (string) get_permalink());

    // "Autores: A, B" — na obra organizada, "Organizadores: …"
    $rotulo_autores = 'Autores';
    $autores = '';

    if ($doi_ativo && $livro) {
        $responsaveis = conexao_doi_organizadores($livro);
        $rotulo_autores = conexao_doi_rotulo_responsaveis($livro);
        $autores = implode(', ', array_column($responsaveis, 'name'));
    }

    if ($autores === '' && $produto && function_exists('conexao_autores')) {
        $rotulo_autores = 'Autores';
        $autores = (string) conexao_autores($produto);
    }

    $resumo = $produto ? trim(wp_strip_all_tags((string) $produto->get_short_description())) : '';

    if ($resumo === '' && ! empty($livro['synopsis'])) {
        $resumo = (string) $livro['synopsis'];
    }

    $descricao = $produto ? (string) $produto->get_description() : '';

    if ($doi_ativo && ! empty($livro['abstract'])) {
        // a página do DOI mostra o resumo científico aprovado no Hub
        $descricao = (string) $livro['abstract'];
    }

    $palavras = array_values(array_filter(array_map('strval', (array) ($livro['keywords'] ?? []))));

    if (! $palavras && $produto) {
        $palavras = wp_list_pluck(get_the_terms($produto->get_id(), 'product_tag') ?: [], 'name');
    }

    $isbn_impresso = (string) ($livro['isbn_print'] ?? '');
    $isbn_ebook = (string) ($livro['isbn_ebook'] ?? '');

    if (! $doi_ativo && $produto) {
        $isbn_impresso = (string) get_post_meta($produto->get_id(), '_conexao_isbn13', true);
    }

    $area = trim((string) ($livro['subject_area'] ?? ''));
    $subarea = trim((string) ($livro['subject_subarea'] ?? ''));
    $doi = (string) ($livro['doi'] ?? '');

    $biblio = array_filter([
        'ISBN (impresso)' => $isbn_impresso,
        'ISBN (e-book)' => $isbn_ebook,
        'DOI' => $doi,
        'Ano de publicação' => (string) ($livro['publication_year'] ?? ''),
        'Edição' => $doi_ativo && $livro ? conexao_doi_edicao_texto($livro) : '',
        'Páginas' => (string) ($livro['pages'] ?? ($produto ? get_post_meta($produto->get_id(), '_conexao_paginas', true) : '')),
        'Formato' => $doi_ativo && ! empty($livro['format']) ? conexao_doi_rotulo_formato((string) $livro['format']) : conexao_livro_formatos_texto($produto),
        'Área' => trim($area.($subarea !== '' ? ' / '.$subarea : '')),
        'Público-alvo' => (string) ($livro['audience'] ?? ''),
    ], static fn ($v) => trim((string) $v) !== '');

    return [
        'produto' => $produto,
        'publicacao_id' => $doi_ativo ? $publicacao_id : 0,
        'dados' => $dados,
        'livro' => $livro,
        'titulo' => $titulo,
        'url' => $url,
        'resumo' => $resumo,
        'rotulo_autores' => $rotulo_autores,
        'autores' => $autores,
        'ficha' => conexao_livro_ficha($produto, $livro),
        'doi' => $doi,
        'isbn_impresso' => $isbn_impresso,
        'isbn_ebook' => $isbn_ebook,
        'palavras' => $palavras,
        'descricao' => $descricao,
        'biblio' => $biblio,
        // a ficha abre pelo pop-up de cadastro (lead); aqui só o post que tem ficha
        'ficha_post' => $doi_ativo && function_exists('conexao_doi_ficha_anexo') && conexao_doi_ficha_anexo($publicacao_id) ? $publicacao_id : 0,
        'licencas' => array_values(array_filter((array) ($livro['licenses'] ?? []), 'is_array')),
    ];
}

/** "Impresso e e-book" a partir dos formatos à venda do produto. */
function conexao_livro_formatos_texto(?WC_Product $produto): string
{
    if (! $produto) {
        return '';
    }

    if (! $produto->is_type('variable')) {
        return $produto->is_virtual() ? 'E-book' : 'Impresso';
    }

    $formatos = [];

    foreach ($produto->get_children() as $filho) {
        $variacao = wc_get_product($filho);

        if ($variacao && function_exists('conexao_formato_variacao')) {
            $formatos[] = conexao_formato_variacao($variacao);
        }
    }

    $impresso = (bool) array_intersect($formatos, ['impresso', 'impresso-e-book']);
    $ebook = (bool) array_intersect($formatos, ['e-book', 'impresso-e-book']);

    return $impresso && $ebook ? 'Impresso e e-book' : ($ebook ? 'E-book' : ($impresso ? 'Impresso' : ''));
}

/**
 * Os oito detalhes com ícone (idade, páginas, idioma, dimensões, editora, data,
 * ISBN-10, ISBN-13): do produto, completados pela publicação quando faltar.
 *
 * @return list<array{icone:string, rotulo:string, valor:string}>
 */
function conexao_livro_ficha(?WC_Product $produto, array $livro): array
{
    $ficha = $produto && function_exists('conexao_ficha_produto') ? conexao_ficha_produto($produto) : [];
    $presentes = array_column($ficha, 'icone');

    $extra = [
        'paginas' => ['N. de páginas', ! empty($livro['pages']) ? $livro['pages'].' páginas' : ''],
        'idioma' => ['Idioma', ! empty($livro['language']) && function_exists('conexao_doi_nome_idioma') ? conexao_doi_nome_idioma((string) $livro['language']) : ''],
        'editora' => ['Editora', (string) ($livro['publisher'] ?? '')],
        'publicacao' => ['Data de publicação', ! empty($livro['publication_date']) && function_exists('conexao_doi_data_br') ? conexao_doi_data_br($livro['publication_date']) : (string) ($livro['publication_year'] ?? '')],
        'isbn13' => ['ISBN-13', (string) ($livro['isbn_print'] ?? $livro['isbn_ebook'] ?? '')],
    ];

    foreach ($extra as $icone => [$rotulo, $valor]) {
        if ($valor !== '' && ! in_array($icone, $presentes, true)) {
            $ficha[] = ['icone' => $icone, 'rotulo' => $rotulo, 'valor' => (string) $valor];
        }
    }

    foreach ($ficha as &$item) {
        $item['valor'] = conexao_livro_valor_ficha($item['icone'], $item['valor']);
    }
    unset($item);

    $ordem = ['idade', 'paginas', 'idioma', 'dimensoes', 'editora', 'publicacao', 'isbn10', 'isbn13'];
    usort($ficha, static fn ($a, $b) => array_search($a['icone'], $ordem, true) <=> array_search($b['icone'], $ordem, true));

    return $ficha;
}

/** Valores no formato do Figma: "20 junho 2026" e "16 x 2 x 23cm". */
function conexao_livro_valor_ficha(string $icone, string $valor): string
{
    if ($icone === 'publicacao' && preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', trim($valor), $m)) {
        $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

        return (int) $m[1].' '.($meses[(int) $m[2] - 1] ?? $m[2]).' '.$m[3];
    }

    if ($icone === 'dimensoes') {
        // wc_format_dimensions() devolve "17&times;24&times;2 cm"
        $valor = html_entity_decode($valor, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace('/\s*cm$/u', 'cm', preg_replace('/\s*[×x]\s*/u', ' x ', $valor) ?? $valor) ?? $valor;
    }

    return $valor;
}

/**
 * Desenha a página do livro. `$produto` é o produto da loja (pode faltar num
 * livro só com DOI); `$publicacao_id`, o post da publicação (0 sem DOI).
 */
function conexao_pagina_livro(?WC_Product $produto, int $publicacao_id = 0): void
{
    if ($produto && $produto->is_type('variable')) {
        // fora da página de produto o WooCommerce não carrega o script dos formatos
        wp_enqueue_script('wc-add-to-cart-variation');
    }

    if ($produto) {
        // estrelas clicáveis no formulário de avaliação (troca o <select> da nota por p.stars)
        wp_enqueue_script('wc-single-product');
    }

    get_template_part('template-parts/livro/pagina', null, conexao_livro_contexto($produto, $publicacao_id));
}

/**
 * Desenha a página do capítulo (Figma 2281:568): mesma grade e mesmas peças da
 * página do livro, com o capítulo no topo e o livro na coluna da direita.
 */
function conexao_pagina_capitulo(WP_Post $post_capitulo): void
{
    $post_livro = conexao_doi_livro_do_capitulo($post_capitulo);
    $livro_id = $post_livro ? $post_livro->ID : 0;
    $produto = conexao_livro_produto_da_publicacao($livro_id);

    if ($produto && $produto->is_type('variable')) {
        wp_enqueue_script('wc-add-to-cart-variation');
    }

    if ($produto) {
        wp_enqueue_script('wc-single-product');
    }

    $contexto = conexao_livro_contexto($produto, $livro_id);
    $contexto['post_livro'] = $post_livro;
    $contexto['post_capitulo'] = $post_capitulo;
    $contexto['capitulo'] = conexao_doi_capitulo($post_capitulo->ID);

    get_template_part('template-parts/livro/capitulo', null, $contexto);
}
