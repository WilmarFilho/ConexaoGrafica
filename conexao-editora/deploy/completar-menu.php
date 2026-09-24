<?php
/**
 * Garante os submenus do menu principal num site já instalado: os filhos de
 * "Livros" e de "Categorias" e a classe que transforma "Autores" no painel com
 * fotos. O setup.sh faz isso num ambiente novo; aqui é para alcançar um site
 * que já estava no ar quando o menu cresceu.
 *
 * Idempotente: roda quantas vezes precisar, só acrescenta o que falta.
 *
 *   wp eval-file completar-menu.php
 */

if (! defined('ABSPATH')) {
    exit;
}

$local = get_nav_menu_locations();
$menu = (int) ($local['principal'] ?? 0);

if (! $menu) {
    echo "! o menu principal não está atribuído\n";
    return;
}

/** Item de primeiro nível pelo título. */
$topo = static function (string $titulo) use ($menu): ?object {
    foreach (wp_get_nav_menu_items($menu) ?: [] as $item) {
        if ((int) $item->menu_item_parent === 0 && $item->title === $titulo) {
            return $item;
        }
    }

    return null;
};

/** Títulos que já pendem de um item. */
$filhos = static function (int $pai) use ($menu): array {
    $titulos = [];

    foreach (wp_get_nav_menu_items($menu) ?: [] as $item) {
        if ((int) $item->menu_item_parent === $pai) {
            $titulos[] = $item->title;
        }
    }

    return $titulos;
};

$adicionar = static function (array $dados, int $pai, string $rotulo) use ($menu): void {
    $id = wp_update_nav_menu_item($menu, 0, $dados + [
        'menu-item-parent-id' => $pai,
        'menu-item-status' => 'publish',
    ]);

    echo is_wp_error($id) ? "  ! $rotulo: ".$id->get_error_message()."\n" : "  + $rotulo\n";
};

// ---- submenu de Livros -----------------------------------------------------
$livros = $topo('Livros');

if ($livros) {
    $existentes = $filhos((int) $livros->ID);

    foreach (['pre-vendas', 'lancamentos', 'mais-vendidos'] as $slug) {
        $pagina = get_page_by_path($slug);

        if (! $pagina) {
            echo "  ! página $slug não existe\n";
            continue;
        }

        if (in_array($pagina->post_title, $existentes, true)) {
            echo "  = {$pagina->post_title}\n";
            continue;
        }

        $adicionar([
            'menu-item-type' => 'post_type',
            'menu-item-object' => 'page',
            'menu-item-object-id' => $pagina->ID,
            'menu-item-title' => $pagina->post_title,
        ], (int) $livros->ID, $pagina->post_title);
    }

    if (in_array('Todos os livros', $existentes, true)) {
        echo "  = Todos os livros (Livros)\n";
    } else {
        $adicionar([
            'menu-item-type' => 'custom',
            'menu-item-url' => '/loja/',
            'menu-item-title' => 'Todos os livros',
            'menu-item-classes' => 'botao',
        ], (int) $livros->ID, 'Todos os livros (Livros)');
    }
} else {
    echo "! item Livros não encontrado\n";
}

// ---- submenu de Categorias -------------------------------------------------
$categorias = $topo('Categorias');

if ($categorias) {
    $existentes = $filhos((int) $categorias->ID);
    $slugs = ['biografia', 'cronica', 'direito', 'educacao-familiar', 'historia', 'medicina', 'literatura', 'religiao'];

    foreach ($slugs as $slug) {
        $termo = get_term_by('slug', $slug, 'product_cat');

        if (! $termo) {
            echo "  ! categoria $slug não existe\n";
            continue;
        }

        if (in_array($termo->name, $existentes, true)) {
            echo "  = {$termo->name}\n";
            continue;
        }

        $adicionar([
            'menu-item-type' => 'taxonomy',
            'menu-item-object' => 'product_cat',
            'menu-item-object-id' => $termo->term_id,
            'menu-item-title' => $termo->name,
        ], (int) $categorias->ID, $termo->name);
    }

    if (in_array('Todos os livros', $existentes, true)) {
        echo "  = Todos os livros (Categorias)\n";
    } else {
        $adicionar([
            'menu-item-type' => 'custom',
            'menu-item-url' => '/loja/',
            'menu-item-title' => 'Todos os livros',
            'menu-item-classes' => 'botao',
        ], (int) $categorias->ID, 'Todos os livros (Categorias)');
    }
} else {
    echo "! item Categorias não encontrado\n";
}

// ---- painel de Autores -----------------------------------------------------
$autores = $topo('Autores');

if ($autores) {
    $classes = array_filter((array) get_post_meta($autores->ID, '_menu_item_classes', true));

    if (in_array('painel-autores', $classes, true)) {
        echo "  = classe painel-autores\n";
    } else {
        $classes[] = 'painel-autores';
        update_post_meta($autores->ID, '_menu_item_classes', array_values($classes));
        echo "  + classe painel-autores\n";
    }
} else {
    echo "! item Autores não encontrado\n";
}

wp_cache_delete('conexao_autores_menu');
