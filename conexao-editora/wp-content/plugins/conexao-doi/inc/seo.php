<?php
/**
 * Cabeçalho das páginas de livro e capítulo: título, descrição, canonical,
 * Open Graph (capa do produto), metatags citation_* do Google Scholar e
 * JSON-LD Book / Chapter. Nessas páginas o Rank Math fica de fora (título,
 * descrição, OG e schema dele seriam duplicados); no resto do site ele segue.
 */

if (! defined('ABSPATH')) {
    exit;
}

function conexao_doi_e_pagina(): bool
{
    return is_singular(['publicacao', 'capitulo']);
}

/** Objeto da página atual: [tipo, post, livro (array), capitulo (array|null), post do livro]. */
function conexao_doi_contexto(): ?array
{
    static $contexto = false; // false = ainda não calculado

    if ($contexto !== false) {
        return $contexto;
    }

    $contexto = null;
    $post = get_queried_object();

    if (! $post instanceof WP_Post) {
        return null;
    }

    if ($post->post_type === 'publicacao') {
        $contexto = ['tipo' => 'livro', 'post' => $post, 'livro' => conexao_doi_livro($post->ID), 'capitulo' => null, 'post_livro' => $post];
    } elseif ($post->post_type === 'capitulo') {
        $livro = conexao_doi_livro_do_capitulo($post);

        $contexto = [
            'tipo' => 'capitulo',
            'post' => $post,
            'livro' => $livro ? conexao_doi_livro($livro->ID) : [],
            'capitulo' => conexao_doi_capitulo($post->ID),
            'post_livro' => $livro,
        ];
    }

    return $contexto;
}

/** Título da aba do navegador. */
function conexao_doi_seo_titulo(): string
{
    $c = conexao_doi_contexto();

    if (! $c) {
        return '';
    }

    $editora = get_bloginfo('name') ?: 'Conexão Editora';

    if ($c['tipo'] === 'livro') {
        return conexao_doi_titulo_completo($c['livro']).' | '.$editora;
    }

    return conexao_doi_titulo_completo($c['capitulo']).' – '.($c['livro']['title'] ?? '').' | '.$editora;
}

/** Descrição: o resumo, cortado em ~160 caracteres. */
function conexao_doi_seo_descricao(): string
{
    $c = conexao_doi_contexto();

    if (! $c) {
        return '';
    }

    $texto = $c['tipo'] === 'livro'
        ? (string) (($c['livro']['synopsis'] ?? '') ?: ($c['livro']['abstract'] ?? ''))
        : (string) ($c['capitulo']['abstract'] ?? '');

    if (trim($texto) === '' && $c['tipo'] === 'capitulo') {
        $texto = sprintf('Capítulo %s de %s, publicado pela %s.', $c['capitulo']['number'] ?? '', $c['livro']['title'] ?? '', $c['livro']['publisher'] ?? 'Conexão Editora');
    }

    $texto = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($texto)));

    if (mb_strlen($texto) > 160) {
        $texto = rtrim(mb_substr($texto, 0, 157)).'…';
    }

    return $texto;
}

// o título nosso vale sobre o do Rank Math (que entra em 15)
add_filter('pre_get_document_title', function ($titulo) {
    return conexao_doi_e_pagina() ? conexao_doi_seo_titulo() : $titulo;
}, 20);

// antes do head do Rank Math (prioridade 1): nessas páginas ele não imprime nada
add_action('wp_head', function (): void {
    if (! conexao_doi_e_pagina()) {
        return;
    }

    remove_all_actions('rank_math/head');

    // o Rank Math tira o <title> do wp_head e põe no próprio head; devolvemos
    if (has_action('wp_head', '_wp_render_title_tag') === false) {
        add_action('rank_math/head', '_wp_render_title_tag', 1);
    }

    remove_action('wp_head', 'rel_canonical');
}, 0);

add_action('wp_head', 'conexao_doi_cabecalho', 2);

function conexao_doi_cabecalho(): void
{
    $c = conexao_doi_contexto();

    if (! $c) {
        return;
    }

    $url = get_permalink($c['post']);
    $descricao = conexao_doi_seo_descricao();
    $capa = $c['post_livro'] ? conexao_doi_capa_url($c['post_livro']->ID, 'large') : '';
    $livro = $c['livro'];
    $obra = $c['tipo'] === 'livro' ? $livro : $c['capitulo'];
    $meta = static function (string $atributo, string $nome, string $conteudo): void {
        if ($conteudo !== '') {
            printf('<meta %s="%s" content="%s">'."\n", $atributo, esc_attr($nome), esc_attr($conteudo));
        }
    };

    echo "\n<!-- Conexão DOI -->\n";
    $meta('name', 'description', $descricao);
    printf('<link rel="canonical" href="%s">'."\n", esc_url($url));

    // Open Graph e Twitter
    $meta('property', 'og:locale', 'pt_BR');
    $meta('property', 'og:type', $c['tipo'] === 'livro' ? 'book' : 'article');
    $meta('property', 'og:site_name', get_bloginfo('name'));
    $meta('property', 'og:title', conexao_doi_titulo_completo($obra));
    $meta('property', 'og:description', $descricao);
    $meta('property', 'og:url', $url);

    if ($capa) {
        $meta('property', 'og:image', $capa);
    }

    if ($c['tipo'] === 'livro') {
        $meta('property', 'book:isbn', (string) ($livro['isbn_print'] ?? ''));
        $meta('property', 'book:release_date', (string) ($livro['publication_date'] ?? ''));
    }

    $meta('name', 'twitter:card', $capa ? 'summary_large_image' : 'summary');
    $meta('name', 'twitter:title', conexao_doi_titulo_completo($obra));
    $meta('name', 'twitter:description', $descricao);

    if ($capa) {
        $meta('name', 'twitter:image', $capa);
    }

    // Google Scholar
    $data = (string) ($obra['publication_date'] ?? $livro['publication_date'] ?? '');
    $data = $data !== '' ? str_replace('-', '/', substr($data, 0, 10)) : (string) ($livro['publication_year'] ?? '');

    $meta('name', 'citation_title', conexao_doi_titulo_completo($obra));

    foreach (conexao_doi_lista($obra['contributors'] ?? []) as $pessoa) {
        $meta('name', 'citation_author', (string) $pessoa['name']);

        if (! empty($pessoa['affiliation']['name'])) {
            $meta('name', 'citation_author_institution', (string) $pessoa['affiliation']['name']);
        }

        if (! empty($pessoa['orcid'])) {
            $meta('name', 'citation_author_orcid', 'https://orcid.org/'.$pessoa['orcid']);
        }
    }

    $meta('name', 'citation_publication_date', $data);
    $meta('name', 'citation_publisher', (string) ($livro['publisher'] ?? ''));
    $meta('name', 'citation_language', (string) ($obra['language'] ?? $livro['language'] ?? ''));
    $meta('name', 'citation_isbn', (string) ($livro['isbn_print'] ?? ''));

    if (! empty($livro['isbn_ebook']) && ($livro['isbn_ebook'] !== ($livro['isbn_print'] ?? ''))) {
        $meta('name', 'citation_isbn', (string) $livro['isbn_ebook']);
    }

    $meta('name', 'citation_doi', (string) ($obra['doi'] ?? ''));

    if ($c['tipo'] === 'capitulo') {
        $meta('name', 'citation_inbook_title', conexao_doi_titulo_completo($livro));
        $meta('name', 'citation_firstpage', (string) ($obra['first_page'] ?? ''));
        $meta('name', 'citation_lastpage', (string) ($obra['last_page'] ?? ''));
    }

    $meta('name', 'citation_keywords', implode('; ', (array) ($obra['keywords'] ?? [])));
    $meta('name', 'citation_abstract_html_url', $url);

    $pdf = conexao_doi_documento_url($obra);

    if ($pdf !== '') {
        $meta('name', 'citation_pdf_url', $pdf);
    }

    echo '<script type="application/ld+json">'.wp_json_encode(conexao_doi_json_ld($c, $url, $capa, $descricao), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).'</script>'."\n";
    echo "<!-- /Conexão DOI -->\n";
}

/** URL do PDF da ficha, quando o Hub já mandou. */
function conexao_doi_documento_url(array $obra): string
{
    foreach ((array) ($obra['documents'] ?? []) as $documento) {
        if (is_array($documento) && ! empty($documento['url'])) {
            return (string) $documento['url'];
        }
    }

    return '';
}

function conexao_doi_json_ld_pessoa(array $pessoa): array
{
    $saida = ['@type' => 'Person', 'name' => (string) $pessoa['name']];

    if (! empty($pessoa['orcid'])) {
        $saida['@id'] = 'https://orcid.org/'.$pessoa['orcid'];
        $saida['sameAs'] = 'https://orcid.org/'.$pessoa['orcid'];
    }

    if (! empty($pessoa['affiliation']['name'])) {
        $saida['affiliation'] = ['@type' => 'Organization', 'name' => (string) $pessoa['affiliation']['name']];
    }

    return $saida;
}

function conexao_doi_json_ld_livro(array $livro, string $url, string $capa, string $descricao): array
{
    $pessoas = conexao_doi_lista($livro['contributors'] ?? []);
    $editores = array_values(array_filter($pessoas, static fn ($p) => ($p['role'] ?? '') === 'editor'));
    $autores = array_values(array_filter($pessoas, static fn ($p) => ($p['role'] ?? 'author') === 'author'));
    $doi = (string) ($livro['doi'] ?? '');

    $saida = [
        '@type' => 'Book',
        '@id' => $url,
        'url' => $url,
        'name' => (string) ($livro['title'] ?? ''),
        'inLanguage' => (string) ($livro['language'] ?? 'pt'),
        'publisher' => ['@type' => 'Organization', 'name' => (string) ($livro['publisher'] ?? 'Conexão Editora')],
    ];

    if (! empty($livro['subtitle'])) {
        $saida['alternativeHeadline'] = (string) $livro['subtitle'];
    }

    if (! empty($livro['alt_title'])) {
        $saida['alternateName'] = (string) $livro['alt_title'];
    }

    if ($descricao !== '') {
        $saida['description'] = $descricao;
    }

    if ($editores) {
        $saida['editor'] = array_map('conexao_doi_json_ld_pessoa', $editores);
    }

    if ($autores) {
        $saida['author'] = array_map('conexao_doi_json_ld_pessoa', $autores);
    }

    foreach (['isbn_print', 'isbn_ebook'] as $campo) {
        if (! empty($livro[$campo])) {
            $saida['isbn'][] = (string) $livro[$campo];
        }
    }

    if (isset($saida['isbn']) && count($saida['isbn']) === 1) {
        $saida['isbn'] = $saida['isbn'][0];
    }

    if (! empty($livro['publication_date'])) {
        $saida['datePublished'] = (string) $livro['publication_date'];
    } elseif (! empty($livro['publication_year'])) {
        $saida['datePublished'] = (string) $livro['publication_year'];
    }

    if (! empty($livro['pages'])) {
        $saida['numberOfPages'] = (int) $livro['pages'];
    }

    if (! empty($livro['edition_number'])) {
        $saida['bookEdition'] = (string) $livro['edition_number'];
    }

    if (! empty($livro['keywords'])) {
        $saida['keywords'] = implode(', ', (array) $livro['keywords']);
    }

    if (! empty($livro['subject_area'])) {
        $saida['about'] = (string) $livro['subject_area'];
    }

    if ($capa !== '') {
        $saida['image'] = $capa;
    }

    if ($doi !== '') {
        $saida['sameAs'] = 'https://doi.org/'.$doi;
        $saida['identifier'] = ['@type' => 'PropertyValue', 'propertyID' => 'DOI', 'value' => $doi];
    }

    return $saida;
}

function conexao_doi_json_ld(array $c, string $url, string $capa, string $descricao): array
{
    $livro = $c['livro'];

    if ($c['tipo'] === 'livro') {
        $saida = ['@context' => 'https://schema.org'] + conexao_doi_json_ld_livro($livro, $url, $capa, $descricao);
        $ofertas = [];

        foreach (conexao_doi_ofertas($c['post']->ID) as $oferta) {
            if ($oferta['disponivel'] && $oferta['preco'] > 0) {
                $ofertas[] = [
                    '@type' => 'Offer',
                    'price' => number_format($oferta['preco'], 2, '.', ''),
                    'priceCurrency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'BRL',
                    'availability' => 'https://schema.org/InStock',
                    'url' => $oferta['loja'] ?: $url,
                    'name' => conexao_doi_rotulo_modalidade($oferta['modality']),
                ];
            }
        }

        if ($ofertas) {
            $saida['offers'] = $ofertas;
        }

        return $saida;
    }

    $capitulo = $c['capitulo'];
    $url_livro = $c['post_livro'] ? get_permalink($c['post_livro']) : '';
    $doi = (string) ($capitulo['doi'] ?? '');

    $saida = [
        '@context' => 'https://schema.org',
        '@type' => 'Chapter',
        '@id' => $url,
        'url' => $url,
        'name' => (string) ($capitulo['title'] ?? ''),
        'inLanguage' => (string) ($capitulo['language'] ?? $livro['language'] ?? 'pt'),
        'publisher' => ['@type' => 'Organization', 'name' => (string) ($livro['publisher'] ?? 'Conexão Editora')],
    ];

    if (! empty($capitulo['subtitle'])) {
        $saida['alternativeHeadline'] = (string) $capitulo['subtitle'];
    }

    if (! empty($capitulo['title_en'])) {
        $saida['alternateName'] = (string) $capitulo['title_en'];
    }

    if ($descricao !== '') {
        $saida['description'] = $descricao;
    }

    $autores = conexao_doi_lista($capitulo['contributors'] ?? []);

    if ($autores) {
        $saida['author'] = array_map('conexao_doi_json_ld_pessoa', $autores);
    }

    if (! empty($capitulo['number'])) {
        $saida['position'] = (string) $capitulo['number'];
    }

    if (! empty($capitulo['first_page'])) {
        $saida['pageStart'] = (string) $capitulo['first_page'];
    }

    if (! empty($capitulo['last_page'])) {
        $saida['pageEnd'] = (string) $capitulo['last_page'];
    }

    $data = (string) ($capitulo['publication_date'] ?? $livro['publication_date'] ?? $livro['publication_year'] ?? '');

    if ($data !== '') {
        $saida['datePublished'] = $data;
    }

    if (! empty($capitulo['keywords'])) {
        $saida['keywords'] = implode(', ', (array) $capitulo['keywords']);
    }

    if ($doi !== '') {
        $saida['sameAs'] = 'https://doi.org/'.$doi;
        $saida['identifier'] = ['@type' => 'PropertyValue', 'propertyID' => 'DOI', 'value' => $doi];
    }

    if ($livro) {
        $saida['isPartOf'] = conexao_doi_json_ld_livro($livro, $url_livro, $capa, '');
    }

    return $saida;
}
