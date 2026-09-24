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

    // coleções e séries: o catálogo filtra por elas quando a editora criar
    register_taxonomy('colecao', ['product'], [
        'labels' => [
            'name' => 'Coleções',
            'singular_name' => 'Coleção',
            'menu_name' => 'Coleções',
            'search_items' => 'Buscar coleções',
            'all_items' => 'Todas as coleções',
            'edit_item' => 'Editar coleção',
            'update_item' => 'Atualizar coleção',
            'add_new_item' => 'Adicionar coleção',
            'new_item_name' => 'Nome da coleção',
            'not_found' => 'Nenhuma coleção encontrada',
        ],
        'public' => true,
        'hierarchical' => false,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'colecoes'],
    ]);
});
