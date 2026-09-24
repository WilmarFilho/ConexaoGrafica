<?php
/**
 * Post do blog: texto à esquerda, mesma coluna lateral da listagem e os
 * relacionados no fim.
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();

    $id = get_the_ID();
    $autor = get_the_author_meta('display_name');
    $categorias = get_the_category();
    $blog = (int) get_option('page_for_posts');

    $relacionados = get_posts([
        'post_type' => 'post',
        'posts_per_page' => 2,
        'post__not_in' => [$id],
        'category__in' => $categorias ? wp_list_pluck($categorias, 'term_id') : [],
        'ignore_sticky_posts' => true,
    ]);

    // sem irmãos de categoria, mostra os mais recentes
    if (! $relacionados) {
        $relacionados = get_posts([
            'post_type' => 'post',
            'posts_per_page' => 2,
            'post__not_in' => [$id],
        ]);
    }
    ?>
    <div class="container pagina pagina--post">
        <?php
        conexao_trilha(get_the_title(), $blog ? [[
            'url' => get_permalink($blog),
            'texto' => get_the_title($blog),
        ]] : []);
        ?>

        <div class="blog-grade">
            <article class="blog__principal post-unico">
                <h1 class="post-unico__titulo"><?php the_title(); ?></h1>

                <p class="post-unico__resumo"><?php echo esc_html(get_the_excerpt()); ?></p>

                <div class="post-unico__cabecalho">
                    <span class="post-card__meta">
                        <span class="post-card__autor">
                            <?php echo get_avatar(get_the_author_meta('ID'), 26, '', $autor, ['class' => 'post-card__avatar']); ?>
                            <?php echo esc_html($autor); ?>
                        </span>

                        <span aria-hidden="true">|</span>
                        <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date()); ?></time>

                        <span aria-hidden="true">|</span>
                        <span><?php echo esc_html(conexao_tempo_leitura($id)); ?> min</span>
                    </span>

                    <?php if ($categorias) : ?>
                        <span class="post-card__cats">
                            <?php foreach (array_slice($categorias, 0, 2) as $categoria) : ?>
                                <a href="<?php echo esc_url(get_category_link($categoria)); ?>"><?php echo esc_html($categoria->name); ?></a>
                            <?php endforeach; ?>
                        </span>
                    <?php endif; ?>

                    <button class="compartilhar" type="button" data-compartilhar
                            data-url="<?php the_permalink(); ?>"
                            data-titulo="<?php echo esc_attr(get_the_title()); ?>"
                            aria-label="Compartilhar este conteúdo">
                        <?php conexao_the_icon('compartilhar', 18); ?>
                    </button>
                </div>

                <?php if (has_post_thumbnail()) : ?>
                    <figure class="post-unico__capa"><?php the_post_thumbnail('large'); ?></figure>
                <?php endif; ?>

                <div class="texto texto-post">
                    <?php the_content(); ?>
                </div>

                <?php if ($relacionados) : ?>
                    <section class="relacionados">
                        <h2>Fique por dentro</h2>

                        <div class="relacionados__grade">
                            <?php foreach ($relacionados as $outro) : ?>
                                <article class="relacionado">
                                    <?php if (has_post_thumbnail($outro)) : ?>
                                        <a class="relacionado__foto" href="<?php echo esc_url(get_permalink($outro)); ?>" tabindex="-1" aria-hidden="true">
                                            <?php echo get_the_post_thumbnail($outro, 'medium_large', ['loading' => 'lazy', 'alt' => '']); ?>
                                        </a>
                                    <?php endif; ?>

                                    <h3><a href="<?php echo esc_url(get_permalink($outro)); ?>"><?php echo esc_html(get_the_title($outro)); ?></a></h3>

                                    <p><?php echo esc_html(wp_trim_words(get_the_excerpt($outro), 28)); ?></p>

                                    <a class="relacionado__mais" href="<?php echo esc_url(get_permalink($outro)); ?>">Ver mais</a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </article>

            <?php get_template_part('template-parts/blog/lateral'); ?>
        </div>
    </div>
    <?php
endwhile;

get_footer();
