<?php
/**
 * Post na listagem do blog.
 */

if (! defined('ABSPATH')) {
    exit;
}

$id = get_the_ID();
$autor = get_the_author_meta('display_name');
$categorias = get_the_category();
?>
<article <?php post_class('post-card'); ?>>
    <?php if (has_post_thumbnail()) : ?>
        <a class="post-card__foto" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
            <?php the_post_thumbnail('large', ['loading' => 'lazy', 'alt' => '']); ?>
        </a>
    <?php endif; ?>

    <div class="post-card__corpo">
        <div class="post-card__topo">
            <h2 class="post-card__titulo"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>

            <button class="compartilhar" type="button" data-compartilhar
                    data-url="<?php the_permalink(); ?>"
                    data-titulo="<?php echo esc_attr(get_the_title()); ?>"
                    aria-label="Compartilhar este conteúdo">
                <?php conexao_the_icon('compartilhar', 18); ?>
            </button>
        </div>

        <p class="post-card__resumo"><?php echo esc_html(get_the_excerpt()); ?></p>

        <footer class="post-card__meta">
            <span class="post-card__autor">
                <?php echo get_avatar(get_the_author_meta('ID'), 26, '', $autor, ['class' => 'post-card__avatar']); ?>
                <?php echo esc_html($autor); ?>
            </span>

            <span aria-hidden="true">·</span>
            <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date()); ?></time>

            <span aria-hidden="true">·</span>
            <span><?php echo esc_html(conexao_tempo_leitura($id)); ?> min</span>

            <?php if ($categorias) : ?>
                <span class="post-card__cats">
                    <?php foreach (array_slice($categorias, 0, 2) as $categoria) : ?>
                        <a href="<?php echo esc_url(get_category_link($categoria)); ?>"><?php echo esc_html($categoria->name); ?></a>
                    <?php endforeach; ?>
                </span>
            <?php endif; ?>
        </footer>
    </div>
</article>
