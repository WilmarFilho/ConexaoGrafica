<?php
/**
 * Autoria dos livros: taxonomia própria, para o site ter página de autor.
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    register_taxonomy('autor', ['product'], [
        'labels' => [
            'name' => 'Autores',
            'singular_name' => 'Autor',
            'menu_name' => 'Autores',
            'search_items' => 'Buscar autores',
            'all_items' => 'Todos os autores',
            'edit_item' => 'Editar autor',
            'update_item' => 'Atualizar autor',
            'add_new_item' => 'Adicionar autor',
            'new_item_name' => 'Nome do autor',
            'not_found' => 'Nenhum autor encontrado',
        ],
        'public' => true,
        'hierarchical' => false,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'autores'],
    ]);
});
