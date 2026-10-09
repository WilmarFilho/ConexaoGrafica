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
    /** O próximo submenu é o painel de categorias (com as subcategorias ao lado). */
    private bool $painel_categorias = false;

    public function start_lvl(&$output, $depth = 0, $args = null): void
    {
        if ($depth === 0 && $this->painel_categorias) {
            $output .= '<ul class="sub-menu sub-menu--categorias" data-painel-categorias>';

            return;
        }

        parent::start_lvl($output, $depth, $args);
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0): void
    {
        if ($depth === 0) {
            $this->painel_categorias = in_array('painel-categorias', (array) $item->classes, true)
                || trim(wp_strip_all_tags($item->title)) === 'Categorias';
        }

        parent::start_el($output, $item, $depth, $args, $id);

        if ($depth === 0 && in_array('painel-autores', (array) $item->classes, true)) {
            $output .= conexao_painel_autores();
        }

        // categoria principal no painel: as subcategorias vão junto, para abrir ao lado
        if ($depth === 1 && $item->object === 'product_cat') {
            $output .= conexao_subcategorias_menu((int) $item->object_id);
        }
    }
}

/** Subcategorias de uma categoria principal, cada uma levando ao catálogo já filtrado. */
function conexao_subcategorias_menu(int $categoria): string
{
    $filhas = get_terms(['taxonomy' => 'product_cat', 'parent' => $categoria, 'hide_empty' => true]);

    if (is_wp_error($filhas) || ! $filhas) {
        return '';
    }

    $ordem = static fn (WP_Term $t): int => (int) get_term_meta($t->term_id, 'order', true);
    usort($filhas, static fn (WP_Term $a, WP_Term $b): int => [$ordem($a), $a->name] <=> [$ordem($b), $b->name]);

    $loja = class_exists('WooCommerce') ? wc_get_page_permalink('shop') : home_url('/loja/');
    $itens = '';

    foreach ($filhas as $filha) {
        $itens .= sprintf(
            '<li><a href="%s">%s</a></li>',
            esc_url(add_query_arg(['cat' => [$filha->slug]], $loja)),
            esc_html($filha->name)
        );
    }

    return '<ul class="subcategorias">'.$itens.'</ul>';
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
