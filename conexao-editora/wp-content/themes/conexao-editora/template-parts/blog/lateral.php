<?php
/**
 * Coluna lateral do blog: busca, categorias, mais lidos, banner, tags e redes.
 */

if (! defined('ABSPATH')) {
    exit;
}

$categorias = get_categories(['hide_empty' => true, 'number' => 12]);
$mais_lidos = get_posts(['post_type' => 'post', 'posts_per_page' => 3, 'orderby' => 'comment_count', 'order' => 'DESC']);
$banner = get_template_directory().'/assets/img/banner-lateral.png';
$tags = get_tags(['number' => 14, 'orderby' => 'count', 'order' => 'DESC']);

$redes = array_filter([
    'instagram' => (string) get_theme_mod('conexao_instagram', ''),
    'facebook' => (string) get_theme_mod('conexao_facebook', ''),
    'youtube' => (string) get_theme_mod('conexao_youtube', ''),
]);
?>
<aside class="lateral" aria-label="Conteúdo complementar">

    <section class="lateral__bloco lateral__bloco--busca">
        <h2 class="lateral__titulo">Busca</h2>

        <form class="busca-lateral" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
            <label class="tela-leitor" for="busca-blog">Buscar no blog</label>
            <input type="search" id="busca-blog" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="Procure por posts...">
            <input type="hidden" name="post_type" value="post">
            <button type="submit" aria-label="Buscar"><?php conexao_the_icon('lupa', 18); ?></button>
        </form>
    </section>

    <?php if ($categorias) : ?>
        <section class="lateral__bloco lateral__bloco--categorias">
            <h2 class="lateral__titulo">Categorias</h2>

            <ul class="etiquetas">
                <?php foreach ($categorias as $categoria) : ?>
                    <li><a href="<?php echo esc_url(get_category_link($categoria)); ?>"><?php echo esc_html($categoria->name); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($mais_lidos) : ?>
        <section class="lateral__bloco lateral__bloco--lidos">
            <h2 class="lateral__titulo">Posts mais lidos</h2>

            <ul class="lateral__lista">
                <?php foreach ($mais_lidos as $post_item) : ?>
                    <li><a href="<?php echo esc_url(get_permalink($post_item)); ?>"><?php echo esc_html(get_the_title($post_item)); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if (file_exists($banner)) : ?>
        <a class="lateral__banner" href="<?php echo esc_url(home_url('/publique-conosco/')); ?>">
            <img src="<?php echo esc_url(get_template_directory_uri().'/assets/img/banner-lateral.png'); ?>"
                 alt="Publique conosco: transforme seu manuscrito em livro"
                 width="310" height="420" loading="lazy">
        </a>
    <?php endif; ?>

    <?php if ($tags) : ?>
        <section class="lateral__bloco lateral__bloco--tags">
            <h2 class="lateral__titulo">Nuvem de tags</h2>

            <p class="nuvem">
                <?php $mais_usada = max(wp_list_pluck($tags, 'count')); ?>
                <?php foreach ($tags as $tag) : ?>
                    <?php /* as mais usadas saem maiores e em azul, como no layout */ ?>
                    <a class="<?php echo $tag->count >= $mais_usada ? 'nuvem--forte' : ''; ?>"
                       href="<?php echo esc_url(get_tag_link($tag)); ?>"
                       style="font-size: <?php echo esc_attr(min(19, 12 + $tag->count)); ?>px"><?php echo esc_html($tag->name); ?></a>
                <?php endforeach; ?>
            </p>
        </section>
    <?php endif; ?>

    <section class="lateral__bloco lateral__bloco--redes">
        <h2 class="lateral__titulo">Siga-nos</h2>

        <ul class="rodape__sociais">
            <li><a href="<?php echo esc_url($redes['instagram'] ?? '#'); ?>" aria-label="Instagram"><?php conexao_the_icon('instagram', 16); ?></a></li>
            <li><a href="<?php echo esc_url($redes['facebook'] ?? '#'); ?>" aria-label="Facebook"><?php conexao_the_icon('facebook', 16); ?></a></li>
            <li><a href="<?php echo esc_url($redes['youtube'] ?? '#'); ?>" aria-label="YouTube"><?php conexao_the_icon('youtube', 16); ?></a></li>
        </ul>
    </section>
</aside>
