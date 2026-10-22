<?php
/**
 * Painel: as publicações aparecem só para consulta. Sem editor, sem botão de
 * salvar; um aviso explica que tudo é editado no Hub Editora. O que alguém
 * conseguir salvar por fora é sobrescrito no próximo PUT.
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_DOI_TIPOS = ['publicacao', 'capitulo'];

add_action('add_meta_boxes', function (string $tipo): void {
    if (! in_array($tipo, CONEXAO_DOI_TIPOS, true)) {
        return;
    }

    remove_meta_box('submitdiv', $tipo, 'side');
    remove_meta_box('slugdiv', $tipo, 'normal');
    remove_meta_box('tagsdiv-autor', $tipo, 'side');

    add_meta_box('conexao-doi-dados', 'Dados recebidos do Hub Editora', 'conexao_doi_metabox_dados', $tipo, 'normal', 'high');
});

add_action('admin_notices', function (): void {
    $tela = get_current_screen();

    if (! $tela || ! in_array($tela->post_type, CONEXAO_DOI_TIPOS, true)) {
        return;
    }

    echo '<div class="notice notice-info"><p><strong>Editado no Hub Editora.</strong> Esta página é publicada pelo Hub Editora e não pode ser alterada aqui: qualquer mudança feita no WordPress é sobrescrita na próxima publicação.</p></div>';
});

function conexao_doi_metabox_dados(WP_Post $post): void
{
    $e_livro = $post->post_type === 'publicacao';
    $dados = $e_livro ? conexao_doi_livro($post->ID) : conexao_doi_capitulo($post->ID);
    $livro_post = $e_livro ? $post : conexao_doi_livro_do_capitulo($post);

    $linhas = [
        'Título' => conexao_doi_titulo_completo($dados),
        'DOI' => (string) get_post_meta($post->ID, CONEXAO_DOI_META_DOI, true),
        'Id no Hub' => (string) get_post_meta($post->ID, CONEXAO_DOI_META_HUB_ID, true),
        'Estado' => (string) get_post_meta($post->ID, CONEXAO_DOI_META_STATUS, true),
        'Estado do DOI' => (string) get_post_meta($post->ID, CONEXAO_DOI_META_DOI_STATUS, true),
        'Endereço' => get_permalink($post),
    ];

    if ($e_livro) {
        $linhas['ISBN impresso'] = (string) get_post_meta($post->ID, CONEXAO_DOI_META_ISBN_PRINT, true);
        $linhas['ISBN e-book'] = (string) get_post_meta($post->ID, CONEXAO_DOI_META_ISBN_EBOOK, true);
        $linhas['Capítulos'] = (string) count(conexao_doi_capitulos_do_livro($post->ID));
        $linhas['Slugs anteriores'] = implode(', ', (array) get_post_meta($post->ID, CONEXAO_DOI_META_SLUG_ANTIGO, false));
        $linhas['Recebido em'] = (string) get_post_meta($post->ID, '_conexao_recebido_em', true);
    } elseif ($livro_post) {
        $linhas['Livro'] = sprintf('<a href="%s">%s</a>', esc_url(get_edit_post_link($livro_post) ?: ''), esc_html($livro_post->post_title));
        $linhas['Sequência'] = (string) get_post_meta($post->ID, CONEXAO_DOI_META_SEQUENCIA, true);
    }

    echo '<table class="widefat striped"><tbody>';

    foreach ($linhas as $rotulo => $valor) {
        if ($valor === '') {
            continue;
        }

        $html = $rotulo === 'Livro' ? $valor : ($rotulo === 'Endereço' ? sprintf('<a href="%1$s" target="_blank">%1$s</a>', esc_url($valor)) : esc_html($valor));
        printf('<tr><th scope="row" style="width:180px">%s</th><td>%s</td></tr>', esc_html($rotulo), $html);
    }

    echo '</tbody></table>';

    $pessoas = conexao_doi_lista($dados['contributors'] ?? []);

    if ($pessoas) {
        echo '<p><strong>Pessoas:</strong> '.esc_html(implode('; ', array_map(static fn ($p) => $p['name'].(! empty($p['orcid']) ? ' ('.$p['orcid'].')' : ''), $pessoas))).'</p>';
    }

    printf(
        '<details><summary>Payload completo (JSON)</summary><pre style="max-height:400px;overflow:auto;background:#f6f7f7;padding:12px">%s</pre></details>',
        esc_html((string) wp_json_encode($e_livro ? conexao_doi_dados_livro($post->ID) : $dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
    );
}

// a lista mostra o que interessa: DOI, id no Hub, estado
foreach (CONEXAO_DOI_TIPOS as $conexao_doi_tipo) {
    add_filter('manage_'.$conexao_doi_tipo.'_posts_columns', function (array $colunas): array {
        unset($colunas['taxonomy-autor'], $colunas['date']);

        return $colunas + ['conexao_doi' => 'DOI', 'conexao_hub' => 'Id no Hub', 'conexao_estado' => 'Estado'];
    });

    add_action('manage_'.$conexao_doi_tipo.'_posts_custom_column', function (string $coluna, int $post_id): void {
        if ($coluna === 'conexao_doi') {
            echo esc_html((string) get_post_meta($post_id, CONEXAO_DOI_META_DOI, true));
        } elseif ($coluna === 'conexao_hub') {
            echo esc_html((string) get_post_meta($post_id, CONEXAO_DOI_META_HUB_ID, true));
        } elseif ($coluna === 'conexao_estado') {
            $estado = (string) get_post_meta($post_id, CONEXAO_DOI_META_STATUS, true);
            echo esc_html($estado === 'unpublished' ? 'Fora de catálogo' : 'Publicado').' · DOI '.esc_html((string) get_post_meta($post_id, CONEXAO_DOI_META_DOI_STATUS, true));
        }
    }, 10, 2);
}

// sem "Edição rápida" nem "Lixeira" na lista: tudo vem do Hub
add_filter('post_row_actions', function (array $acoes, WP_Post $post): array {
    if (in_array($post->post_type, CONEXAO_DOI_TIPOS, true)) {
        unset($acoes['inline hide-if-no-js'], $acoes['trash']);
        $acoes['edit'] = sprintf('<a href="%s">Consultar</a>', esc_url(get_edit_post_link($post) ?: ''));
    }

    return $acoes;
}, 10, 2);
