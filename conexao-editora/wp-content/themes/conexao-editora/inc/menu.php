<?php
/**
 * Painéis que abrem no menu principal.
 *
 * O item marcado com a classe "painel-autores" não usa submenu comum: ele
 * recebe a grade de autores com foto, montada aqui.
 */

if (! defined('ABSPATH')) {
    exit;
}

class Conexao_Menu_Walker extends Walker_Nav_Menu
{
    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0): void
    {
        parent::start_el($output, $item, $depth, $args, $id);

        if ($depth === 0 && in_array('painel-autores', (array) $item->classes, true)) {
            $output .= conexao_painel_autores();
        }
    }
}

/** Autores em destaque: os que têm foto, na ordem definida no painel. */
function conexao_autores_destaque(int $quantidade = 8): array
{
    $termos = get_terms([
        'taxonomy' => 'autor',
        'hide_empty' => false,
        'number' => $quantidade,
        'meta_key' => 'conexao_ordem',
        'orderby' => 'meta_value_num',
        'order' => 'ASC',
        'meta_query' => [['key' => 'conexao_foto', 'compare' => 'EXISTS']],
    ]);

    return is_wp_error($termos) ? [] : $termos;
}

/** Grade de autores do menu, com o botão no fim. */
function conexao_painel_autores(): string
{
    $autores = conexao_autores_destaque();

    if (! $autores) {
        return '';
    }

    $itens = '';

    foreach ($autores as $autor) {
        $foto = (int) get_term_meta($autor->term_id, 'conexao_foto', true);
        $imagem = $foto ? wp_get_attachment_image($foto, 'thumbnail', false, ['alt' => '', 'loading' => 'lazy']) : '';

        $itens .= sprintf(
            '<li class="autor-destaque"><a href="%s">%s<span>%s</span></a></li>',
            esc_url(get_term_link($autor)),
            $imagem, // phpcs:ignore WordPress.Security.EscapeOutput
            esc_html($autor->name)
        );
    }

    return sprintf(
        '<ul class="sub-menu sub-menu--autores"><li class="autores-grade"><ul class="autores-lista">%s</ul></li>
         <li class="botao"><a href="%s">Todos os autores</a></li></ul>',
        $itens,
        esc_url(home_url('/autores/'))
    );
}
