<?php
/**
 * Eventos: grade de cards (foto, data e local, título e resumo), seis por
 * página, e o convite para planejar um evento com a editora.
 */

if (! defined('ABSPATH')) {
    exit;
}

$todos = conexao_eventos_ordenados();
$paginas = max(1, (int) ceil(count($todos) / CONEXAO_EVENTOS_POR_PAGINA));
$atual = min($paginas, max(1, (int) get_query_var('paged'), (int) get_query_var('page')));
$eventos = array_slice($todos, ($atual - 1) * CONEXAO_EVENTOS_POR_PAGINA, CONEXAO_EVENTOS_POR_PAGINA);
$base = get_permalink();
$contato = add_query_arg('assunto', 'evento', home_url('/contato/'));

get_header();
?>
<div class="container pagina pagina--eventos">
    <?php conexao_trilha(get_the_title()); ?>

    <h1 class="tela-leitor"><?php the_title(); ?></h1>

    <?php if ($eventos) : ?>
        <div class="eventos-grade">
            <?php foreach ($eventos as $id) : ?>
                <?php $link = get_permalink($id); ?>
                <article class="evento-card">
                    <a class="evento-card__foto" href="<?php echo esc_url($link); ?>" tabindex="-1" aria-hidden="true">
                        <?php
                        if (has_post_thumbnail($id)) {
                            echo get_the_post_thumbnail($id, 'medium_large', ['loading' => 'lazy', 'alt' => '']);
                        }
                        ?>
                    </a>

                    <?php $quando = conexao_quando_onde_evento($id); ?>
                    <?php if ($quando) : ?>
                        <p class="evento-card__data"><?php conexao_the_icon('calendario', 16); ?> <?php echo esc_html($quando); ?></p>
                    <?php endif; ?>

                    <h2 class="evento-card__titulo"><a href="<?php echo esc_url($link); ?>"><?php echo esc_html(get_the_title($id)); ?></a></h2>

                    <?php $resumo = get_the_excerpt($id); ?>
                    <?php if ($resumo) : ?>
                        <p class="evento-card__resumo"><?php echo esc_html(wp_trim_words($resumo, 28)); ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($paginas > 1) : ?>
            <?php
            $numeros = paginate_links([
                'base' => trailingslashit($base).'%_%',
                'format' => 'page/%#%/',
                'current' => $atual,
                'total' => $paginas,
                'mid_size' => 2,
                'end_size' => 1,
                'prev_next' => false,
            ]);
            $pagina_link = static fn (int $n): string => $n > 1 ? trailingslashit($base).'page/'.$n.'/' : $base;
            ?>
            <nav class="navigation pagination" aria-label="Páginas de eventos">
                <div class="nav-links">
                    <?php if ($atual > 1) : ?>
                        <a class="prev page-numbers" href="<?php echo esc_url($pagina_link($atual - 1)); ?>">Anterior</a>
                    <?php else : ?>
                        <span class="prev page-numbers desativado" aria-hidden="true">Anterior</span>
                    <?php endif; ?>

                    <?php echo wp_kses_post($numeros); ?>

                    <?php if ($atual < $paginas) : ?>
                        <a class="next page-numbers" href="<?php echo esc_url($pagina_link($atual + 1)); ?>">Próxima</a>
                    <?php else : ?>
                        <span class="next page-numbers desativado" aria-hidden="true">Próxima</span>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>

        <p class="blog__contador"><?php printf('Pág. %d-%d', (int) $atual, (int) $paginas); ?></p>
    <?php else : ?>
        <p class="eventos-vazio">Nenhum evento publicado no momento. Fique de olho: em breve divulgamos a próxima agenda.</p>
    <?php endif; ?>

    <p class="eventos-cta">
        <a class="btn btn--azul" href="<?php echo esc_url($contato); ?>">Planejar meu evento com a Conexão</a>
    </p>
</div>
<?php
get_footer();
