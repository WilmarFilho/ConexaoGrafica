<?php
/**
 * Post do blog.
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();
    ?>
    <article class="container pagina pagina--post">
        <header class="pagina__topo">
            <p class="pagina__eyebrow"><?php echo esc_html(get_the_date('j \d\e F, Y')); ?></p>
            <h1><?php the_title(); ?></h1>
        </header>

        <?php if (has_post_thumbnail()) : ?>
            <figure class="pagina__capa"><?php the_post_thumbnail('large'); ?></figure>
        <?php endif; ?>

        <div class="texto">
            <?php the_content(); ?>
        </div>
    </article>
    <?php
endwhile;

get_footer();
