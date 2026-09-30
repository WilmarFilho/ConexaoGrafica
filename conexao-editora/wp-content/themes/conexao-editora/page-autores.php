<?php
/**
 * Autores: banner com filtro por inicial e a grade de cards (foto, biografia
 * e os livros de cada um), nove por página.
 */

if (! defined('ABSPATH')) {
    exit;
}

$letra = isset($_GET['letra']) ? strtoupper(sanitize_key(wp_unslash($_GET['letra']))) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$letra = preg_match('/^[A-Z]$/', $letra) ? $letra : '';

$lista = conexao_autores_lista($letra);
$total = count($lista['autores']);
$paginas = max(1, (int) ceil($total / CONEXAO_AUTORES_POR_PAGINA));
$atual = min($paginas, max(1, (int) get_query_var('paged'), (int) get_query_var('page')));
$autores = array_slice($lista['autores'], ($atual - 1) * CONEXAO_AUTORES_POR_PAGINA, CONEXAO_AUTORES_POR_PAGINA);

$base = get_permalink();
$link_letra = static fn (string $l): string => $l === '' ? $base : add_query_arg('letra', strtolower($l), $base);

get_header();
?>
<div class="container pagina pagina--autores">
    <?php conexao_trilha(get_the_title()); ?>

    <section class="autores-hero">
        <h1><?php the_title(); ?></h1>
        <p>Conheça os autores que fazem parte do nosso catálogo e explore suas obras.</p>

        <nav class="autores-letras" aria-label="Filtrar autores pela inicial">
            <div class="autores-letras__trilho" data-letras>
                <a class="autores-letras__todos<?php echo $letra === '' ? ' e-atual' : ''; ?>" href="<?php echo esc_url($link_letra('')); ?>"<?php echo $letra === '' ? ' aria-current="page"' : ''; ?>>Todos</a>

                <?php foreach (range('A', 'Z') as $l) : ?>
                    <?php if (! empty($lista['iniciais'][$l])) : ?>
                        <a class="autores-letras__letra<?php echo $letra === $l ? ' e-atual' : ''; ?>" href="<?php echo esc_url($link_letra($l)); ?>"
                           <?php echo $letra === $l ? 'aria-current="page"' : ''; ?> aria-label="Autores com a letra <?php echo esc_attr($l); ?>"><?php echo esc_html($l); ?></a>
                    <?php else : ?>
                        <span class="autores-letras__letra autores-letras__letra--vazia" aria-hidden="true"><?php echo esc_html($l); ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <button class="autores-letras__seta" type="button" data-letras-seta aria-label="Ver mais letras">
                <?php conexao_the_icon('seta-direita', 18); ?>
            </button>
        </nav>
    </section>

    <?php if ($autores) : ?>
        <div class="autores-grade">
            <?php foreach ($autores as $autor) : ?>
                <?php
                $link = get_term_link($autor);
                $bio = trim(wp_strip_all_tags(term_description($autor)));
                $livros = conexao_livros_do_autor($autor);
                ?>
                <article class="autor-card">
                    <a class="autor-card__foto" href="<?php echo esc_url($link); ?>" tabindex="-1" aria-hidden="true">
                        <?php echo conexao_foto_autor($autor); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    </a>

                    <h2 class="autor-card__nome"><a href="<?php echo esc_url($link); ?>"><?php echo esc_html($autor->name); ?></a></h2>

                    <p class="autor-card__bio">
                        <?php
                        echo esc_html($bio !== ''
                            ? $bio
                            : sprintf(_n('%d livro no catálogo da Conexão Editora.', '%d livros no catálogo da Conexão Editora.', $autor->count, 'conexao'), $autor->count));
                        ?>
                    </p>

                    <?php if ($livros) : ?>
                        <h3 class="autor-card__titulo">Livros do autor</h3>

                        <ul class="autor-card__livros">
                            <?php foreach ($livros as $livro) : ?>
                                <?php $nomes = wp_get_post_terms($livro->get_id(), 'autor', ['fields' => 'names']); ?>
                                <li class="autor-livro">
                                    <a class="autor-livro__capa" href="<?php echo esc_url($livro->get_permalink()); ?>" tabindex="-1" aria-hidden="true">
                                        <?php echo $livro->get_image('woocommerce_thumbnail', ['loading' => 'lazy']); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                    </a>
                                    <div class="autor-livro__texto">
                                        <a class="autor-livro__nome" href="<?php echo esc_url($livro->get_permalink()); ?>"><?php echo esc_html($livro->get_name()); ?></a>
                                        <?php if ($nomes && ! is_wp_error($nomes)) : ?>
                                            <span class="autor-livro__autores"><?php echo esc_html(implode(', ', $nomes)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <?php if ($autor->count > count($livros)) : ?>
                            <a class="autor-card__todos" href="<?php echo esc_url($link); ?>">Ver os <?php echo esc_html((string) $autor->count); ?> livros</a>
                        <?php endif; ?>
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
                'add_args' => $letra !== '' ? ['letra' => strtolower($letra)] : [],
            ]);
            $pagina_link = static fn (int $n): string => add_query_arg($letra !== '' ? ['letra' => strtolower($letra)] : [], $n > 1 ? trailingslashit($base).'page/'.$n.'/' : $base);
            ?>
            <nav class="navigation pagination" aria-label="Páginas de autores">
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
        <p class="autores-vazio">
            Nenhum autor com a letra <?php echo esc_html($letra); ?>.
            <a href="<?php echo esc_url($base); ?>">Ver todos os autores</a>
        </p>
    <?php endif; ?>
</div>
<?php
get_footer();
