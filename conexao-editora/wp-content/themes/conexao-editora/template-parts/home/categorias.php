<?php
/**
 * Atalhos para as categorias de livro. Clicar filtra a vitrine logo abaixo;
 * sem JavaScript, o link leva para a página da categoria.
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! taxonomy_exists('product_cat')) {
    return;
}

// As oito do layout. Com o catálogo antigo migrado existem muitas outras
// categorias, e a home não deve listar todas.
$preferidas = ['biografia', 'cronica', 'direito', 'educacao-familiar', 'historia', 'literatura', 'medicina', 'religiao'];

$categorias = get_terms([
    'taxonomy' => 'product_cat',
    'hide_empty' => false,
    'slug' => $preferidas,
    'orderby' => 'name',
]);

if (is_wp_error($categorias) || count($categorias) < 4) {
    $categorias = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'number' => 8,
        'orderby' => 'count',
        'order' => 'DESC',
        'exclude' => [(int) get_option('default_product_cat')],
    ]);
}

if (is_wp_error($categorias) || ! $categorias) {
    return;
}
?>
<section class="categorias">
    <div class="container">
        <?php conexao_secao_titulo('Categorias em destaque', class_exists('WooCommerce') ? wc_get_page_permalink('shop') : ''); ?>

        <ul class="categorias__lista" data-filtro-categorias>
            <?php foreach ($categorias as $categoria) : ?>
                <li>
                    <a class="categoria" href="<?php echo esc_url(get_term_link($categoria)); ?>"
                       data-categoria="<?php echo esc_attr($categoria->slug); ?>" aria-pressed="false">
                        <?php
                        $svg = conexao_svg_categoria($categoria->slug);

                        if ($svg) {
                            echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput
                        } else {
                            conexao_the_icon(conexao_icone_categoria($categoria->slug), 34);
                        }
                        ?>
                        <span><?php echo esc_html($categoria->name); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php conexao_secao_rodape(class_exists('WooCommerce') ? wc_get_page_permalink('shop') : ''); ?>
    </div>
</section>
