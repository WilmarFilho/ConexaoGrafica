<?php
/**
 * Últimos posts do blog: um destaque grande e dois menores.
 */

if (! defined('ABSPATH')) {
    exit;
}

$posts = get_posts(['numberposts' => 3, 'post_status' => 'publish']);

if (! $posts) {
    return;
}

$destaque = array_shift($posts);
?>
<section class="conteudos">
    <div class="container">
        <?php conexao_secao_titulo('Conteúdos que conectam ideias', get_permalink(get_option('page_for_posts')) ?: home_url('/conteudos/')); ?>

        <div class="conteudos__grade">
            <?php conexao_card_post($destaque, true); ?>

            <div class="conteudos__coluna">
                <?php foreach ($posts as $post_item) : ?>
                    <?php conexao_card_post($post_item); ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
