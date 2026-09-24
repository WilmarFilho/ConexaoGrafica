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

    <div class="blog-grade">
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
                $atual = max(1, (int) get_query_var('paged'));
                $paginas = max(1, (int) $wp_query->max_num_pages);
                $numeros = paginate_links([
                    'mid_size' => 4,
                    'prev_next' => false,
                    'type' => 'plain',
                ]);
                ?>

                <?php if ($paginas > 1) : ?>
                    <?php /* Anterior e Próxima aparecem sempre; nas pontas viram texto apagado */ ?>
                    <nav class="navigation pagination" aria-label="Navegação dos posts">
                        <div class="nav-links">
                            <?php if ($atual > 1) : ?>
                                <a class="prev page-numbers" href="<?php echo esc_url(get_pagenum_link($atual - 1)); ?>">Anterior</a>
                            <?php else : ?>
                                <span class="prev page-numbers desativado" aria-hidden="true">Anterior</span>
                            <?php endif; ?>

                            <?php echo wp_kses_post($numeros); ?>

                            <?php if ($atual < $paginas) : ?>
                                <a class="next page-numbers" href="<?php echo esc_url(get_pagenum_link($atual + 1)); ?>">Próxima</a>
                            <?php else : ?>
                                <span class="next page-numbers desativado" aria-hidden="true">Próxima</span>
                            <?php endif; ?>
                        </div>
                    </nav>
                <?php endif; ?>

                <p class="blog__contador">
                    <?php
                    printf(
                        'Pág. %d-%d',
                        max(1, (int) get_query_var('paged')),
                        max(1, (int) $wp_query->max_num_pages)
                    );
                    ?>
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
