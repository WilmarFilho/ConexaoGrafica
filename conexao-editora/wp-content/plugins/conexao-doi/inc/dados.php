<?php
/**
 * Gravação e leitura dos dados que o Hub Editora envia. O Hub manda sempre o
 * registro completo e o WordPress substitui: nada aqui mescla com o que já
 * estava salvo. Gravar duas vezes o mesmo payload não duplica nada.
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_DOI_META_PESSOA_HUB = '_conexao_hub_person_id';
const CONEXAO_DOI_META_PESSOA_ORCID = '_conexao_orcid';

// ---------------------------------------------------------------- leitura

/** Payload inteiro guardado no livro (book + chapters), já decodificado. */
function conexao_doi_dados_livro(int $post_id): array
{
    static $memoria = [];

    if (! isset($memoria[$post_id])) {
        $json = (string) get_post_meta($post_id, CONEXAO_DOI_META_DADOS, true);
        $dados = $json !== '' ? json_decode($json, true) : null;
        $memoria[$post_id] = is_array($dados) ? $dados : ['book' => [], 'chapters' => []];
    }

    return $memoria[$post_id];
}

/** Só o objeto `book` do livro. */
function conexao_doi_livro(int $post_id): array
{
    return conexao_doi_dados_livro($post_id)['book'] ?? [];
}

/** Objeto `chapter` guardado no post do capítulo. */
function conexao_doi_capitulo(int $post_id): array
{
    static $memoria = [];

    if (! isset($memoria[$post_id])) {
        $json = (string) get_post_meta($post_id, CONEXAO_DOI_META_DADOS, true);
        $dados = $json !== '' ? json_decode($json, true) : null;
        $memoria[$post_id] = is_array($dados) ? $dados : [];
    }

    return $memoria[$post_id];
}

function conexao_doi_livro_do_capitulo(WP_Post $capitulo): ?WP_Post
{
    $livro = $capitulo->post_parent ? get_post($capitulo->post_parent) : null;

    return ($livro && $livro->post_type === 'publicacao') ? $livro : null;
}

/** O livro está fora de catálogo (despublicado no Hub)? */
function conexao_doi_fora_de_catalogo(int $livro_id): bool
{
    return get_post_meta($livro_id, CONEXAO_DOI_META_STATUS, true) === 'unpublished';
}

function conexao_doi_post_por_hub_id(string $tipo, int $hub_id): ?WP_Post
{
    if ($hub_id < 1) {
        return null;
    }

    $posts = get_posts([
        'post_type' => $tipo,
        // inclui a lixeira: capítulo que saiu e voltou reaproveita o post (mesmo wp_post_id)
        'post_status' => ['publish', 'draft', 'pending', 'private', 'future', 'trash'],
        'numberposts' => 1,
        'meta_key' => CONEXAO_DOI_META_HUB_ID,
        'meta_value' => $hub_id,
    ]);

    return $posts[0] ?? null;
}

// ---------------------------------------------------------------- gravação

/**
 * Cria ou atualiza livro, capítulos, pessoas e documentos a partir do payload
 * do PUT. Devolve o corpo da resposta do contrato (seção 4) ou WP_Error.
 */
function conexao_doi_gravar_publicacao(array $payload)
{
    $livro = $payload['book'] ?? null;
    $capitulos = $payload['chapters'] ?? [];

    if (! is_array($livro) || empty($livro['hub_id']) || empty($livro['title']) || empty($livro['slug'])) {
        return new WP_Error('conexao_doi_payload', 'O payload precisa de book.hub_id, book.title e book.slug.', ['status' => 422]);
    }

    if (! is_array($capitulos)) {
        return new WP_Error('conexao_doi_payload', 'chapters precisa ser uma lista.', ['status' => 422]);
    }

    $hub_id = (int) $livro['hub_id'];
    $existente = conexao_doi_post_por_hub_id('publicacao', $hub_id);
    $slug_pedido = sanitize_title((string) $livro['slug']);
    $doi_status = (string) ($livro['doi_status'] ?? 'none');
    $slug = $slug_pedido;

    if ($existente && $existente->post_name !== $slug_pedido) {
        $travado = conexao_doi_slug_travado($doi_status)
            || conexao_doi_slug_travado((string) get_post_meta($existente->ID, CONEXAO_DOI_META_DOI_STATUS, true));

        if ($travado) {
            // DOI já enviado: o endereço é permanente, o slug novo é ignorado
            $slug = $existente->post_name;
            $livro['slug'] = $slug;
        } else {
            add_post_meta($existente->ID, CONEXAO_DOI_META_SLUG_ANTIGO, $existente->post_name);
        }
    }

    $data = conexao_doi_data_post((string) ($livro['publication_date'] ?? ''), (int) ($livro['publication_year'] ?? 0));

    $post = [
        'post_type' => 'publicacao',
        'post_status' => 'publish',
        'post_title' => wp_strip_all_tags((string) $livro['title']),
        'post_name' => $slug,
        'post_content' => conexao_doi_texto((string) ($livro['abstract'] ?? '')),
        'post_excerpt' => conexao_doi_texto((string) ($livro['synopsis'] ?? '')),
        'post_date' => $data,
        'post_date_gmt' => get_gmt_from_date($data),
        'comment_status' => 'closed',
        'ping_status' => 'closed',
    ];

    if ($existente) {
        $post['ID'] = $existente->ID;
    }

    kses_remove_filters();
    $livro_id = wp_insert_post(wp_slash($post), true);
    kses_init_filters();

    if (is_wp_error($livro_id)) {
        return $livro_id;
    }

    // o slug que valeu (o WP pode ter acrescentado -2 num conflito); se ele
    // voltou a ser um slug antigo, sai da lista de antigos
    $livro['slug'] = get_post_field('post_name', $livro_id);
    delete_post_meta($livro_id, CONEXAO_DOI_META_SLUG_ANTIGO, $livro['slug']);

    $pessoas = [];
    $termos_livro = [];

    foreach (conexao_doi_lista($livro['contributors'] ?? []) as $pessoa) {
        $termo = conexao_doi_termo_pessoa($pessoa);

        if ($termo) {
            $pessoas[(int) $pessoa['hub_person_id']] = $termo;
            $termos_livro[] = $termo;
        }
    }

    wp_set_object_terms($livro_id, array_values(array_unique($termos_livro)), 'autor');

    // capítulos: cria/atualiza os do payload e cuida dos que sumiram
    $resposta_capitulos = [];
    $ids_capitulos = [];

    foreach ($capitulos as $indice => $capitulo) {
        if (! is_array($capitulo) || empty($capitulo['hub_id']) || empty($capitulo['title'])) {
            return new WP_Error('conexao_doi_payload', sprintf('chapters[%d] precisa de hub_id e title.', $indice), ['status' => 422]);
        }

        $resultado = conexao_doi_gravar_capitulo($livro_id, $livro, $capitulo, $pessoas);

        if (is_wp_error($resultado)) {
            return $resultado;
        }

        [$capitulo_id, $capitulos[$indice]] = $resultado;
        $ids_capitulos[] = $capitulo_id;
        $resposta_capitulos[] = [
            'hub_chapter_id' => (int) $capitulo['hub_id'],
            'wp_post_id' => $capitulo_id,
            'url' => get_permalink($capitulo_id),
        ];
    }

    conexao_doi_capitulos_ausentes($livro_id, $ids_capitulos);

    // metas do livro: o payload inteiro e o que precisa de consulta
    $status = in_array($livro['status'] ?? '', ['unpublished'], true) ? 'unpublished' : 'published';

    update_post_meta($livro_id, CONEXAO_DOI_META_HUB_ID, $hub_id);
    update_post_meta($livro_id, CONEXAO_DOI_META_DOI, (string) ($livro['doi'] ?? ''));
    update_post_meta($livro_id, CONEXAO_DOI_META_ISBN_PRINT, (string) ($livro['isbn_print'] ?? ''));
    update_post_meta($livro_id, CONEXAO_DOI_META_ISBN_EBOOK, (string) ($livro['isbn_ebook'] ?? ''));
    update_post_meta($livro_id, CONEXAO_DOI_META_STATUS, $status);
    update_post_meta($livro_id, CONEXAO_DOI_META_DOI_STATUS, $doi_status);
    update_post_meta($livro_id, CONEXAO_DOI_META_AREA, (string) ($livro['subject_area'] ?? ''));
    update_post_meta($livro_id, CONEXAO_DOI_META_DADOS, wp_slash(wp_json_encode(
        ['book' => $livro, 'chapters' => array_values($capitulos)],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    )));
    update_post_meta($livro_id, '_conexao_recebido_em', current_time('mysql'));

    $resposta_pessoas = [];

    foreach ($pessoas as $hub_person_id => $termo) {
        $resposta_pessoas[] = ['hub_person_id' => $hub_person_id, 'wp_term_id' => $termo];
    }

    return [
        'book' => ['wp_post_id' => $livro_id, 'url' => get_permalink($livro_id)],
        'chapters' => $resposta_capitulos,
        'people' => $resposta_pessoas,
    ];
}

/**
 * Um capítulo do payload vira post filho do livro.
 *
 * @param array<int, int> $pessoas hub_person_id → term_id, alimentado aqui também
 * @return array{0: int, 1: array}|WP_Error o post e o capítulo (com o slug que valeu)
 */
function conexao_doi_gravar_capitulo(int $livro_id, array $livro, array $capitulo, array &$pessoas)
{
    $hub_id = (int) $capitulo['hub_id'];
    $existente = conexao_doi_post_por_hub_id('capitulo', $hub_id);
    $sequencia = max(1, (int) ($capitulo['sequence'] ?? 0));
    $data = conexao_doi_data_post(
        (string) ($capitulo['publication_date'] ?? $livro['publication_date'] ?? ''),
        (int) ($livro['publication_year'] ?? 0)
    );

    $post = [
        'post_type' => 'capitulo',
        'post_status' => 'publish',
        'post_parent' => $livro_id,
        'post_title' => wp_strip_all_tags((string) $capitulo['title']),
        'post_name' => sanitize_title((string) ($capitulo['slug'] ?: $capitulo['title'])),
        'post_content' => conexao_doi_texto((string) ($capitulo['abstract'] ?? '')),
        'post_date' => $data,
        'post_date_gmt' => get_gmt_from_date($data),
        'menu_order' => $sequencia,
        'comment_status' => 'closed',
        'ping_status' => 'closed',
    ];

    if ($existente) {
        $post['ID'] = $existente->ID;
    }

    kses_remove_filters();
    $capitulo_id = wp_insert_post(wp_slash($post), true);
    kses_init_filters();

    if (is_wp_error($capitulo_id)) {
        return $capitulo_id;
    }

    $capitulo['slug'] = get_post_field('post_name', $capitulo_id);
    $termos = [];

    foreach (conexao_doi_lista($capitulo['contributors'] ?? []) as $pessoa) {
        $termo = conexao_doi_termo_pessoa($pessoa);

        if ($termo) {
            $pessoas[(int) $pessoa['hub_person_id']] = $termo;
            $termos[] = $termo;
        }
    }

    wp_set_object_terms($capitulo_id, array_values(array_unique($termos)), 'autor');

    update_post_meta($capitulo_id, CONEXAO_DOI_META_HUB_ID, $hub_id);
    update_post_meta($capitulo_id, CONEXAO_DOI_META_DOI, (string) ($capitulo['doi'] ?? ''));
    update_post_meta($capitulo_id, CONEXAO_DOI_META_SEQUENCIA, $sequencia);
    update_post_meta($capitulo_id, CONEXAO_DOI_META_DOI_STATUS, (string) ($capitulo['doi_status'] ?? 'none'));
    update_post_meta($capitulo_id, CONEXAO_DOI_META_STATUS, 'published');
    update_post_meta($capitulo_id, CONEXAO_DOI_META_DADOS, wp_slash(wp_json_encode($capitulo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));

    return [$capitulo_id, $capitulo];
}

/**
 * Capítulo que o Hub deixou de mandar: se o DOI nunca saiu, vai para a
 * lixeira; se já foi depositado, a página fica (regra da Crossref), marcada
 * como fora de catálogo.
 */
function conexao_doi_capitulos_ausentes(int $livro_id, array $mantidos): void
{
    foreach (conexao_doi_capitulos_do_livro($livro_id) as $capitulo) {
        if (in_array($capitulo->ID, $mantidos, true)) {
            continue;
        }

        if (conexao_doi_slug_travado((string) get_post_meta($capitulo->ID, CONEXAO_DOI_META_DOI_STATUS, true))) {
            update_post_meta($capitulo->ID, CONEXAO_DOI_META_STATUS, 'unpublished');
            continue;
        }

        wp_trash_post($capitulo->ID);
    }
}

/** Despublicar: o livro sai do catálogo, a página continua. */
function conexao_doi_despublicar(int $hub_id)
{
    $livro = conexao_doi_post_por_hub_id('publicacao', $hub_id);

    if (! $livro) {
        return new WP_Error('conexao_doi_nao_encontrado', 'Publicação não encontrada no WordPress.', ['status' => 404]);
    }

    update_post_meta($livro->ID, CONEXAO_DOI_META_STATUS, 'unpublished');

    $dados = conexao_doi_dados_livro($livro->ID);
    $dados['book']['status'] = 'unpublished';
    update_post_meta($livro->ID, CONEXAO_DOI_META_DADOS, wp_slash(wp_json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));

    return conexao_doi_estado($hub_id);
}

/** Estado atual no WordPress, para o Hub conferir. */
function conexao_doi_estado(int $hub_id)
{
    $livro = conexao_doi_post_por_hub_id('publicacao', $hub_id);

    if (! $livro) {
        return new WP_Error('conexao_doi_nao_encontrado', 'Publicação não encontrada no WordPress.', ['status' => 404]);
    }

    $capitulos = [];

    foreach (conexao_doi_capitulos_do_livro($livro->ID) as $capitulo) {
        $capitulos[] = [
            'hub_chapter_id' => (int) get_post_meta($capitulo->ID, CONEXAO_DOI_META_HUB_ID, true),
            'wp_post_id' => $capitulo->ID,
            'url' => get_permalink($capitulo),
            'sequence' => (int) get_post_meta($capitulo->ID, CONEXAO_DOI_META_SEQUENCIA, true),
            'doi' => (string) get_post_meta($capitulo->ID, CONEXAO_DOI_META_DOI, true),
            'status' => (string) get_post_meta($capitulo->ID, CONEXAO_DOI_META_STATUS, true),
        ];
    }

    $dados = conexao_doi_dados_livro($livro->ID);
    $pessoas = [];

    foreach (conexao_doi_pessoas_da_publicacao($dados) as $pessoa) {
        $termo = conexao_doi_termo_por_hub_id((int) $pessoa['hub_person_id']);
        $pessoas[] = ['hub_person_id' => (int) $pessoa['hub_person_id'], 'wp_term_id' => $termo ? $termo->term_id : null];
    }

    return [
        'book' => [
            'wp_post_id' => $livro->ID,
            'url' => get_permalink($livro),
            'slug' => $livro->post_name,
            'old_slugs' => array_values(array_unique((array) get_post_meta($livro->ID, CONEXAO_DOI_META_SLUG_ANTIGO, false))),
            'doi' => (string) get_post_meta($livro->ID, CONEXAO_DOI_META_DOI, true),
            'status' => (string) get_post_meta($livro->ID, CONEXAO_DOI_META_STATUS, true),
            'doi_status' => (string) get_post_meta($livro->ID, CONEXAO_DOI_META_DOI_STATUS, true),
            'received_at' => (string) get_post_meta($livro->ID, '_conexao_recebido_em', true),
            'documents' => $dados['book']['documents'] ?? [],
        ],
        'chapters' => $capitulos,
        'people' => $pessoas,
    ];
}

// ---------------------------------------------------------------- pessoas

/**
 * A pessoa vira termo da taxonomia `autor`: reaproveita pelo hub_person_id,
 * depois pelo nome; senão cria. Nome e ORCID são sempre os do Hub.
 */
function conexao_doi_termo_pessoa(array $pessoa): int
{
    $nome = trim((string) ($pessoa['name'] ?? ''));
    $hub_person_id = (int) ($pessoa['hub_person_id'] ?? 0);

    if ($nome === '' || ! taxonomy_exists('autor')) {
        return 0;
    }

    $termo = $hub_person_id ? conexao_doi_termo_por_hub_id($hub_person_id) : null;

    if (! $termo) {
        $pelo_nome = get_term_by('name', $nome, 'autor');
        $termo = $pelo_nome instanceof WP_Term ? $pelo_nome : null;
    }

    if ($termo) {
        if ($termo->name !== $nome) {
            wp_update_term($termo->term_id, 'autor', ['name' => $nome]);
        }

        $termo_id = $termo->term_id;
    } else {
        $novo = wp_insert_term($nome, 'autor');

        if (is_wp_error($novo)) {
            // nome já existe com outro slug, por exemplo: usa o que o erro aponta
            $termo_id = (int) ($novo->get_error_data('term_exists') ?? 0);

            if (! $termo_id) {
                return 0;
            }
        } else {
            $termo_id = (int) $novo['term_id'];
        }
    }

    if ($hub_person_id) {
        update_term_meta($termo_id, CONEXAO_DOI_META_PESSOA_HUB, $hub_person_id);
    }

    $orcid = trim((string) ($pessoa['orcid'] ?? ''));

    if ($orcid !== '') {
        update_term_meta($termo_id, CONEXAO_DOI_META_PESSOA_ORCID, $orcid);
    } else {
        delete_term_meta($termo_id, CONEXAO_DOI_META_PESSOA_ORCID);
    }

    return $termo_id;
}

function conexao_doi_termo_por_hub_id(int $hub_person_id): ?WP_Term
{
    if ($hub_person_id < 1 || ! taxonomy_exists('autor')) {
        return null;
    }

    $termos = get_terms([
        'taxonomy' => 'autor',
        'hide_empty' => false,
        'number' => 1,
        'meta_key' => CONEXAO_DOI_META_PESSOA_HUB,
        'meta_value' => $hub_person_id,
    ]);

    return (! is_wp_error($termos) && $termos) ? $termos[0] : null;
}

/** Todas as pessoas do payload (livro e capítulos), uma vez cada, por hub_person_id. */
function conexao_doi_pessoas_da_publicacao(array $dados): array
{
    $pessoas = [];

    foreach (conexao_doi_lista($dados['book']['contributors'] ?? []) as $pessoa) {
        $pessoas[(int) $pessoa['hub_person_id']] = $pessoa;
    }

    foreach ($dados['chapters'] ?? [] as $capitulo) {
        foreach (conexao_doi_lista($capitulo['contributors'] ?? []) as $pessoa) {
            $pessoas[(int) $pessoa['hub_person_id']] = $pessoa;
        }
    }

    return array_values($pessoas);
}

// ---------------------------------------------------------------- apoio

/** Lista de contribuições ordenada por `sequence`, só as que têm nome. */
function conexao_doi_lista($contributors): array
{
    if (! is_array($contributors)) {
        return [];
    }

    $lista = array_values(array_filter($contributors, static fn ($c) => is_array($c) && ! empty($c['name'])));

    usort($lista, static fn ($a, $b) => (int) ($a['sequence'] ?? 0) <=> (int) ($b['sequence'] ?? 0));

    return $lista;
}

/** Texto do Hub vem sem HTML; qualquer tag que escape cai aqui. */
function conexao_doi_texto(string $texto): string
{
    return trim(wp_strip_all_tags($texto));
}

/**
 * Data do post: a de publicação, ou 1º de janeiro do ano, ou agora. Data no
 * futuro vira agora, senão o WordPress agendaria o post em vez de publicar.
 */
function conexao_doi_data_post(string $data, int $ano): string
{
    $agora = current_time('mysql');
    $escolhida = '';

    if ($data !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $data)) {
        $escolhida = substr($data, 0, 10).' 00:00:00';
    } elseif ($ano > 0) {
        $escolhida = sprintf('%04d-01-01 00:00:00', $ano);
    }

    return ($escolhida === '' || $escolhida > $agora) ? $agora : $escolhida;
}
