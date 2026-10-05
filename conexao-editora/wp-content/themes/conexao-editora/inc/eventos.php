<?php
/**
 * Eventos da editora: lançamentos, palestras, masterclasses. Cada evento tem
 * datas, local e, se houver, o link de inscrição. A listagem fica na página
 * /eventos/ (page-eventos.php); cada evento tem sua página em /evento/nome/.
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_EVENTOS_POR_PAGINA = 6;

add_action('init', function (): void {
    register_post_type('evento', [
        'labels' => [
            'name' => 'Eventos',
            'singular_name' => 'Evento',
            'menu_name' => 'Eventos',
            'add_new' => 'Adicionar evento',
            'add_new_item' => 'Adicionar evento',
            'edit_item' => 'Editar evento',
            'new_item' => 'Novo evento',
            'view_item' => 'Ver evento',
            'search_items' => 'Buscar eventos',
            'not_found' => 'Nenhum evento encontrado',
            'not_found_in_trash' => 'Nenhum evento na lixeira',
            'all_items' => 'Todos os eventos',
            'featured_image' => 'Foto do evento',
            'set_featured_image' => 'Escolher foto do evento',
        ],
        'public' => true,
        // a listagem é a página /eventos/; sem arquivo próprio para não disputar o endereço
        'has_archive' => false,
        'rewrite' => ['slug' => 'evento', 'with_front' => false],
        'menu_icon' => 'dashicons-calendar-alt',
        'menu_position' => 21,
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
        'show_in_rest' => true,
    ]);
});

/** Campos do evento: [chave => [rótulo, tipo, ajuda]]. */
function conexao_campos_evento(): array
{
    return [
        '_evento_inicio' => ['Data de início', 'date', ''],
        '_evento_fim' => ['Data de término', 'date', 'Em branco para evento de um dia só.'],
        '_evento_local' => ['Local', 'text', 'Ex.: Goiânia, GO'],
        '_evento_link' => ['Link de inscrição', 'url', 'Opcional. Aparece como botão na página do evento.'],
    ];
}

add_action('add_meta_boxes_evento', function (): void {
    add_meta_box('conexao-evento', 'Data, local e inscrição', function (WP_Post $post): void {
        wp_nonce_field('conexao_evento', 'conexao_evento_nonce');
        echo '<table class="form-table" role="presentation"><tbody>';

        foreach (conexao_campos_evento() as $chave => [$rotulo, $tipo, $ajuda]) {
            printf(
                '<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input type="%3$s" id="%1$s" name="%1$s" value="%4$s" class="regular-text">%5$s</td></tr>',
                esc_attr($chave),
                esc_html($rotulo),
                esc_attr($tipo),
                esc_attr((string) get_post_meta($post->ID, $chave, true)),
                $ajuda !== '' ? '<p class="description">'.esc_html($ajuda).'</p>' : ''
            );
        }

        echo '</tbody></table>';
        echo '<p class="description">A foto do evento é a "Foto do evento" (imagem destacada); o texto curto do card é o "Resumo".</p>';
    }, 'evento', 'normal', 'high');
});

add_action('save_post_evento', function (int $id): void {
    if (! isset($_POST['conexao_evento_nonce'])
        || ! wp_verify_nonce(sanitize_key(wp_unslash($_POST['conexao_evento_nonce'])), 'conexao_evento')
        || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        || ! current_user_can('edit_post', $id)) {
        return;
    }

    foreach (conexao_campos_evento() as $chave => [, $tipo]) {
        $bruto = isset($_POST[$chave]) ? trim((string) wp_unslash($_POST[$chave])) : '';

        $valor = match ($tipo) {
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $bruto) ? $bruto : '',
            'url' => esc_url_raw($bruto, ['http', 'https']),
            default => sanitize_text_field($bruto),
        };

        if ($valor === '') {
            delete_post_meta($id, $chave);
        } else {
            update_post_meta($id, $chave, $valor);
        }
    }
});

// coluna de data na lista do painel
add_filter('manage_evento_posts_columns', function (array $colunas): array {
    $novas = [];

    foreach ($colunas as $chave => $rotulo) {
        $novas[$chave] = $rotulo;

        if ($chave === 'title') {
            $novas['evento_data'] = 'Quando';
            $novas['evento_local'] = 'Onde';
        }
    }

    unset($novas['date']);

    return $novas;
});

add_action('manage_evento_posts_custom_column', function (string $coluna, int $id): void {
    if ($coluna === 'evento_data') {
        echo esc_html(conexao_data_evento($id) ?: '—');
    } elseif ($coluna === 'evento_local') {
        echo esc_html((string) get_post_meta($id, '_evento_local', true) ?: '—');
    }
}, 10, 2);

/**
 * "18 a 20 de setembro de 2026", "30 de setembro a 2 de outubro de 2026",
 * "10 de outubro de 2026".
 */
function conexao_data_evento(int $id): string
{
    $inicio = (string) get_post_meta($id, '_evento_inicio', true);
    $fim = (string) get_post_meta($id, '_evento_fim', true);

    if ($inicio === '') {
        return '';
    }

    $a = strtotime($inicio.' 12:00');
    $b = $fim !== '' ? strtotime($fim.' 12:00') : $a;

    if (! $a) {
        return '';
    }

    if (! $b || $b <= $a) {
        return wp_date('j \d\e F \d\e Y', $a);
    }

    if (wp_date('Y-m', $a) === wp_date('Y-m', $b)) {
        return wp_date('j', $a).' a '.wp_date('j \d\e F \d\e Y', $b);
    }

    if (wp_date('Y', $a) === wp_date('Y', $b)) {
        return wp_date('j \d\e F', $a).' a '.wp_date('j \d\e F \d\e Y', $b);
    }

    return wp_date('j \d\e F \d\e Y', $a).' a '.wp_date('j \d\e F \d\e Y', $b);
}

/** Data e local juntos, como no card: "18 a 20 de setembro de 2026 · Goiânia, GO". */
function conexao_quando_onde_evento(int $id): string
{
    return implode(' · ', array_filter([conexao_data_evento($id), (string) get_post_meta($id, '_evento_local', true)]));
}

/**
 * Eventos publicados na ordem da listagem: primeiro os próximos (do mais perto
 * ao mais longe), depois os que já passaram (do mais recente ao mais antigo).
 *
 * @return int[]
 */
function conexao_eventos_ordenados(): array
{
    $ids = get_posts(['post_type' => 'evento', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => true]);
    $hoje = wp_date('Y-m-d');
    $proximos = [];
    $passados = [];

    foreach ($ids as $id) {
        $inicio = (string) get_post_meta($id, '_evento_inicio', true) ?: get_the_date('Y-m-d', $id);
        $fim = (string) get_post_meta($id, '_evento_fim', true) ?: $inicio;

        if ($fim >= $hoje) {
            $proximos[$id] = $inicio;
        } else {
            $passados[$id] = $inicio;
        }
    }

    asort($proximos);
    arsort($passados);

    return array_map('intval', array_merge(array_keys($proximos), array_keys($passados)));
}
