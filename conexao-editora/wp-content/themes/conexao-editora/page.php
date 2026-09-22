<?php
/**
 * Página comum.
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();
    ?>
    <article class="container pagina">
        <header class="pagina__topo">
            <h1><?php the_title(); ?></h1>
        </header>

        <div class="texto">
            <?php the_content(); ?>
        </div>
    </article>
    <?php
endwhile;

get_footer();
