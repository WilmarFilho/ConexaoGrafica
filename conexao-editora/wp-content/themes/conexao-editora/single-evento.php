<?php
/**
 * Página de um evento: foto, quando e onde, o texto e, se houver, o botão de
 * inscrição. Embaixo, o convite para planejar um evento com a editora.
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();

$eventos = get_page_by_path('eventos');

while (have_posts()) :
    the_post();

    $id = get_the_ID();
    $quando = conexao_quando_onde_evento($id);
    $inscricao = (string) get_post_meta($id, '_evento_link', true);
    ?>
    <div class="container pagina pagina--evento">
        <?php
        conexao_trilha(get_the_title(), $eventos ? [[
            'url' => get_permalink($eventos),
            'texto' => get_the_title($eventos),
        ]] : []);
        ?>

        <article class="evento-unico">
            <?php if ($quando) : ?>
                <p class="evento-card__data"><?php conexao_the_icon('calendario', 16); ?> <?php echo esc_html($quando); ?></p>
            <?php endif; ?>

            <h1 class="post-unico__titulo"><?php the_title(); ?></h1>

            <?php if (has_post_thumbnail()) : ?>
                <figure class="evento-unico__foto"><?php the_post_thumbnail('large'); ?></figure>
            <?php endif; ?>

            <div class="texto texto-post">
                <?php the_content(); ?>
            </div>

            <p class="evento-unico__acoes">
                <?php if ($inscricao !== '') : ?>
                    <a class="btn btn--azul" href="<?php echo esc_url($inscricao); ?>" target="_blank" rel="noopener noreferrer">Quero me inscrever</a>
                <?php endif; ?>

                <?php if ($eventos) : ?>
                    <a class="btn btn--contorno" href="<?php echo esc_url(get_permalink($eventos)); ?>">Ver todos os eventos</a>
                <?php endif; ?>
            </p>
        </article>

        <p class="eventos-cta">
            <a class="btn btn--azul" href="<?php echo esc_url(add_query_arg('assunto', 'evento', home_url('/contato/'))); ?>">Planejar meu evento com a Conexão</a>
        </p>
    </div>
    <?php
endwhile;

get_footer();
