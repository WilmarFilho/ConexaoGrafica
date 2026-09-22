<?php
/**
 * Listagem padrão: blog, arquivos e busca.
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();
?>
<div class="container pagina">
    <header class="pagina__topo">
        <h1>
            <?php
            if (is_search()) {
                printf('Resultados para "%s"', esc_html(get_search_query()));
            } elseif (is_archive()) {
                the_archive_title();
            } else {
                echo esc_html(get_the_title(get_option('page_for_posts')) ?: 'Conteúdos');
            }
            ?>
        </h1>
    </header>

    <?php if (have_posts()) : ?>
        <div class="lista-posts">
            <?php
            while (have_posts()) :
                the_post();
                conexao_card_post(get_post());
            endwhile;
            ?>
        </div>

        <?php the_posts_pagination(['mid_size' => 1, 'prev_text' => 'Anterior', 'next_text' => 'Próxima']); ?>
    <?php else : ?>
        <p class="vazio">Nada encontrado por aqui. Tente outra busca.</p>
        <?php get_search_form(); ?>
    <?php endif; ?>
</div>
<?php
get_footer();
