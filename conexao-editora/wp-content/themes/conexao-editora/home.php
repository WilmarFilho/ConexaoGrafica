<?php
/**
 * Listagem do blog (a página escolhida em Leitura → Página de posts).
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();

$pagina = (int) get_option('page_for_posts');
global $wp_query;
?>
<div class="container pagina pagina--blog">
    <?php conexao_trilha($pagina ? get_the_title($pagina) : 'Blog'); ?>

    <div class="blog">
        <div class="blog__principal">
            <header class="blog__cabecalho">
                <h1><?php echo esc_html($pagina ? get_the_title($pagina) : 'Blog'); ?></h1>
                <p>Conteúdos sobre o mundo dos livros, bastidores da edição e novidades do catálogo da Conexão Editora.</p>
            </header>

            <?php if (have_posts()) : ?>
                <div class="blog__lista">
                    <?php
                    while (have_posts()) :
                        the_post();
                        get_template_part('template-parts/blog/card');
                    endwhile;
                    ?>
                </div>

                <?php
                the_posts_pagination([
                    'mid_size' => 2,
                    'prev_text' => 'Anterior',
                    'next_text' => 'Próxima',
                    'screen_reader_text' => 'Navegação dos posts',
                ]);
                ?>

                <p class="blog__contador">
                    Pág. <?php echo esc_html(max(1, (int) get_query_var('paged'))); ?>
                    de <?php echo esc_html(max(1, (int) $wp_query->max_num_pages)); ?>
                </p>
            <?php else : ?>
                <p class="vazio">Nenhum conteúdo publicado ainda.</p>
            <?php endif; ?>
        </div>

        <?php get_template_part('template-parts/blog/lateral'); ?>
    </div>
</div>
<?php
get_footer();
