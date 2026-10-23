<?php
/**
 * Estante e livros. O direito de leitura e o arquivo são os da biblioteca do
 * site (conexao-biblioteca); os metadados vêm da publicação DOI ligada ao
 * produto (conexao-doi) e, sem ela, da taxonomia `autor` e dos campos do
 * produto (tema). A capa é a imagem do produto na loja.
 */

if (! defined('ABSPATH')) {
    exit;
}

/** As dependências estão ativas? Sem a biblioteca o app não tem o que mostrar. */
function conexao_app_biblioteca_ok(): bool
{
    return function_exists('conexao_biblioteca_livros_do_usuario') && function_exists('wc_get_product');
}

/** Dados do livro na publicação DOI ligada ao produto ([] se não houver). */
function conexao_app_dados_doi(int $produto_id): array
{
    if (! function_exists('conexao_doi_publicacao_do_produto') || ! function_exists('conexao_doi_livro')) {
        return [];
    }

    $publicacao = conexao_doi_publicacao_do_produto($produto_id);

    return $publicacao ? ['_post' => $publicacao] + conexao_doi_livro($publicacao) : [];
}

/** Texto puro: sem HTML, sem shortcode, entidades resolvidas, parágrafos preservados. */
function conexao_app_texto(string $html): string
{
    $texto = str_replace(["\r\n", "\r"], "\n", strip_shortcodes($html));
    $texto = preg_replace('#<(br|/p|/li|/h[1-6])\s*/?>#i', "\n", $texto);
    $texto = html_entity_decode(wp_strip_all_tags($texto), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $texto = preg_replace("/[ \t]+/u", ' ', (string) $texto);

    return trim((string) preg_replace("/\n\s*\n\s*\n+/u", "\n\n", (string) $texto));
}

function conexao_app_autores(WC_Product $produto, array $doi): array
{
    if ($doi && function_exists('conexao_doi_organizadores')) {
        $nomes = array_map(static fn ($p) => trim((string) $p['name']), conexao_doi_organizadores($doi));
        $nomes = array_values(array_filter($nomes));

        if ($nomes) {
            return $nomes;
        }
    }

    $termos = taxonomy_exists('autor') ? wp_get_post_terms($produto->get_id(), 'autor', ['fields' => 'names']) : [];

    return is_wp_error($termos) ? [] : array_values(array_map('html_entity_decode', $termos));
}

function conexao_app_capa(WC_Product $produto, array $doi): string
{
    $imagem = (int) $produto->get_image_id();

    if ($imagem) {
        return (string) wp_get_attachment_image_url($imagem, 'woocommerce_single');
    }

    if ($doi && function_exists('conexao_doi_capa_url')) {
        return conexao_doi_capa_url((int) $doi['_post'], 'large');
    }

    return '';
}

/** "16 × 23 cm": as duas maiores medidas do produto (a terceira é a lombada). */
function conexao_app_dimensoes(WC_Product $produto): ?string
{
    $medidas = array_filter(
        array_map('floatval', [$produto->get_length(), $produto->get_width(), $produto->get_height()]),
        static fn ($m) => $m > 0
    );

    if (count($medidas) < 2) {
        return null;
    }

    rsort($medidas);
    $duas = [$medidas[1], $medidas[0]];
    $unidade = (string) get_option('woocommerce_dimension_unit', 'cm');

    return implode(' × ', array_map(static fn ($m) => rtrim(rtrim(number_format($m, 1, ',', ''), '0'), ','), $duas)).' '.$unidade;
}

function conexao_app_ficha(WC_Product $produto, array $doi): array
{
    $meta = static fn (string $chave): string => trim((string) get_post_meta($produto->get_id(), $chave, true));

    $paginas = (int) ($doi['pages'] ?? 0) ?: (int) preg_replace('/\D/', '', $meta('_conexao_paginas'));

    $idioma = '';
    if (! empty($doi['language'])) {
        $idioma = function_exists('conexao_doi_nome_idioma') ? conexao_doi_nome_idioma((string) $doi['language']) : (string) $doi['language'];
    }
    $idioma = $idioma ?: $meta('_conexao_idioma') ?: 'Português';

    $ano = (int) ($doi['publication_year'] ?? 0);
    if (! $ano && preg_match('/\b(\d{4})\b/', $meta('_conexao_publicacao'), $m)) {
        $ano = (int) $m[1];
    }
    if (! $ano && $produto->get_date_created()) {
        $ano = (int) $produto->get_date_created()->date('Y');
    }

    // o app lê o e-book: o ISBN do e-book vem primeiro
    $isbn = (string) ($doi['isbn_ebook'] ?? '') ?: (string) ($doi['isbn_print'] ?? '') ?: $meta('_conexao_isbn13') ?: $meta('_conexao_isbn10');
    $doi_codigo = (string) ($doi['doi'] ?? '');

    return [
        'paginas' => $paginas ?: null,
        'idioma' => $idioma,
        'editora' => (string) ($doi['publisher'] ?? '') ?: $meta('_conexao_editora') ?: 'Conexão Editora',
        'ano' => $ano ?: null,
        'isbn' => $isbn !== '' ? $isbn : null,
        'doi' => $doi_codigo !== '' ? $doi_codigo : null,
        'dimensoes' => conexao_app_dimensoes($produto),
        'pagina_doi' => $doi ? (string) get_permalink((int) $doi['_post']) : null,
    ];
}

/** Progresso no formato do contrato (null se a pessoa ainda não abriu o livro). */
function conexao_app_progresso(?object $linha): ?array
{
    if (! $linha || (int) $linha->total_paginas < 1) {
        return null;
    }

    $pagina = (int) $linha->pagina;
    $total = (int) $linha->total_paginas;

    return [
        'pagina' => $pagina,
        'total_paginas' => $total,
        'percentual' => round(min(100, $pagina / $total * 100), 1),
        'atualizado_em' => conexao_app_iso((string) $linha->atualizado_em),
    ];
}

/**
 * O `Livro` do contrato.
 *
 * @param object|null|false $leitura linha da tabela de leitura; false = buscar
 */
function conexao_app_livro(int $produto_id, int $usuario_id, bool $completo, $leitura = false): ?array
{
    $produto_id = conexao_biblioteca_produto_base($produto_id);
    $produto = wc_get_product($produto_id);

    if (! $produto || $produto->is_type('variation')) {
        return null;
    }

    $doi = conexao_app_dados_doi($produto_id);
    $arquivo = conexao_biblioteca_livro($produto_id);
    $pode = conexao_biblioteca_pode_ler($usuario_id, $produto_id);

    if ($leitura === false) {
        $leitura = conexao_app_leitura_linha($usuario_id, $produto_id);
    }

    $atualizado = $arquivo['modelo'] === 'pdf' ? (int) @filemtime($arquivo['pdf']) : 0;
    $atualizado = $atualizado ?: ($produto->get_date_modified() ? $produto->get_date_modified()->getTimestamp() : 0);
    $media = (float) $produto->get_average_rating();

    $livro = [
        'id' => $produto_id,
        'titulo' => (string) ($doi['title'] ?? '') ?: html_entity_decode($produto->get_name(), ENT_QUOTES, 'UTF-8'),
        'subtitulo' => (string) ($doi['subtitle'] ?? ''),
        'autores' => conexao_app_autores($produto, $doi),
        'capa' => conexao_app_capa($produto, $doi),
        'modelo' => $arquivo['modelo'],
        'disponivel' => $pode && $arquivo['modelo'] !== '',
        'tamanho_bytes' => $arquivo['modelo'] === 'pdf' ? (int) @filesize($arquivo['pdf']) : null,
        'atualizado_em' => $atualizado ? wp_date('c', $atualizado) : null,
        // flipbook (legado): o app abre o leitor do site no navegador interno
        'leitor_url' => $arquivo['modelo'] === 'flipbook' && function_exists('conexao_biblioteca_url_leitor') ? conexao_biblioteca_url_leitor($produto_id) : null,
        'progresso' => conexao_app_progresso($leitura ?: null),
        'avaliacao' => ['media' => round($media, 1), 'quantidade' => (int) $produto->get_review_count()],
    ];

    if ($completo) {
        $descricao = (string) ($doi['synopsis'] ?? '') ?: (string) ($doi['abstract'] ?? '')
            ?: $produto->get_description() ?: $produto->get_short_description();
        $livro['descricao'] = conexao_app_texto($descricao);
        $livro['ficha'] = conexao_app_ficha($produto, $doi);
    }

    return $livro;
}

// ---------------------------------------------------------------- rotas

/** GET /estante */
function conexao_app_rota_estante(WP_REST_Request $request)
{
    if (! conexao_app_biblioteca_ok()) {
        return conexao_app_erro('indisponivel');
    }

    $usuario_id = get_current_user_id();
    $direitos = conexao_biblioteca_livros_do_usuario($usuario_id);
    $leituras = conexao_app_leituras_do_usuario($usuario_id);

    // mais recente primeiro (compra ou concessão)
    uasort($direitos, static fn ($a, $b) => strcmp((string) ($b['data'] ?? ''), (string) ($a['data'] ?? '')));

    $livros = [];
    $continuar = null;
    $mais_recente = '';

    foreach (array_keys($direitos) as $produto_id) {
        $leitura = $leituras[(int) $produto_id] ?? null;
        $livro = conexao_app_livro((int) $produto_id, $usuario_id, false, $leitura);

        if (! $livro) {
            continue;
        }

        $livros[] = $livro;

        // continuar = o último lido que ainda não chegou ao fim
        if ($leitura && $livro['progresso'] && $livro['progresso']['percentual'] < 100 && $livro['disponivel']
            && (string) $leitura->atualizado_em > $mais_recente) {
            $mais_recente = (string) $leitura->atualizado_em;
            $continuar = $livro;
        }
    }

    return ['livros' => $livros, 'continuar' => $continuar];
}

/**
 * Produto do pedido: precisa existir e ser um livro digital (ou estar na
 * estante da pessoa). Devolve o id base ou WP_Error 404.
 */
function conexao_app_produto_da_rota(WP_REST_Request $request)
{
    if (! conexao_app_biblioteca_ok()) {
        return conexao_app_erro('indisponivel');
    }

    $produto_id = conexao_biblioteca_produto_base((int) $request['id']);
    $post = get_post($produto_id);

    if (! $post || $post->post_type !== 'product') {
        return conexao_app_erro('nao_encontrado', 'Livro não encontrado.');
    }

    $na_estante = isset(conexao_biblioteca_livros_do_usuario(get_current_user_id())[$produto_id]);

    if (! $na_estante && ($post->post_status !== 'publish' || ! conexao_biblioteca_e_digital($produto_id))) {
        return conexao_app_erro('nao_encontrado', 'Livro não encontrado.');
    }

    return $produto_id;
}

/** GET /livros/{id} */
function conexao_app_rota_livro(WP_REST_Request $request)
{
    $produto_id = conexao_app_produto_da_rota($request);

    if (is_wp_error($produto_id)) {
        return $produto_id;
    }

    return conexao_app_livro($produto_id, get_current_user_id(), true) ?? conexao_app_erro('nao_encontrado', 'Livro não encontrado.');
}

/** GET|HEAD /livros/{id}/pdf: a mesma entrega da biblioteca (com Range). */
function conexao_app_rota_pdf(WP_REST_Request $request)
{
    $produto_id = conexao_app_produto_da_rota($request);

    if (is_wp_error($produto_id)) {
        return $produto_id;
    }

    $usuario = wp_get_current_user();

    if (! conexao_biblioteca_pode_ler($usuario->ID, $produto_id)) {
        return conexao_app_erro('sem_acesso');
    }

    $arquivo = conexao_biblioteca_livro($produto_id);

    if ($arquivo['modelo'] !== 'pdf' || ! $arquivo['pdf']) {
        return conexao_app_erro('nao_encontrado', $arquivo['modelo'] === 'flipbook'
            ? 'Este livro abre no leitor do site.'
            : 'Este livro ainda está sendo preparado para leitura.');
    }

    // a entrega sai por fora do servidor REST (exit), então o CORS vai aqui
    conexao_app_envia_cors();
    header('X-Robots-Tag: noindex, nofollow');
    // percent-encoded (UTF-8): cabeçalho HTTP não carrega acento com segurança
    header('X-Conexao-Marca: '.rawurlencode(conexao_app_marca($usuario)));

    conexao_biblioteca_entrega($arquivo['pdf'], 'application/pdf');

    return null; // não chega aqui: a entrega encerra a requisição
}
