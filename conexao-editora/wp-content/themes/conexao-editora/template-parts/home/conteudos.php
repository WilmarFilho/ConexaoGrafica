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
$bolhas = get_template_directory().'/assets/img/bolhas.png';
$img = get_template_directory_uri().'/assets/img/';
?>
<section class="conteudos">
    <?php if (file_exists($bolhas)) : ?>
        <img class="conteudos__bolhas conteudos__bolhas--esq" src="<?php echo esc_url($img.'bolhas.png'); ?>" alt="" aria-hidden="true" loading="lazy">
        <img class="conteudos__bolhas conteudos__bolhas--dir" src="<?php echo esc_url($img.'bolhas.png'); ?>" alt="" aria-hidden="true" loading="lazy">
    <?php endif; ?>

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
