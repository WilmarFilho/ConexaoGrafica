<?php
/**
 * Catálogo: cabeçalho com o banner, categorias em destaque, filtros à esquerda
 * e a grade de livros à direita.
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();

global $wp_query;

$destaques = ['biografia', 'cronica', 'direito', 'educacao-familiar', 'historia', 'medicina', 'literatura', 'religiao'];
$categorias_destaque = get_terms([
    'taxonomy' => 'product_cat',
    'slug' => $destaques,
    'hide_empty' => false,
    'orderby' => 'name',
]);

$ordem_atual = isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : 'date'; // phpcs:ignore WordPress.Security.NonceVerification
$busca = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$visao = (isset($_GET['visao']) && $_GET['visao'] === 'lista') ? 'lista' : 'grade'; // phpcs:ignore WordPress.Security.NonceVerification
$faixa = conexao_faixa_precos();
$publique = get_page_by_path('publique-conosco');
$arte_banner = get_template_directory().'/assets/img/catalogo-banner.svg';
?>
<div class="container pagina pagina--catalogo">
    <?php conexao_trilha('Catálogo'); ?>

    <div class="catalogo-topo">
        <div class="catalogo-topo__texto">
            <h1>Catálogo</h1>
            <p>Explore nosso catálogo completo e encontre livros por tema, autor, coleção ou palavra-chave.<br>
                Use os filtros para refinar sua busca e descobrir conteúdos que conectam conhecimento e pessoas.</p>
        </div>

        <aside class="catalogo-banner">
            <?php conexao_logo('rodape'); ?>

            <div class="catalogo-banner__texto">
                <p class="catalogo-banner__titulo">Publique conosco</p>
                <p>Transforme seu original em livro com a Conexão Editora.</p>
                <a class="btn btn--azul btn--pequeno" href="<?php echo esc_url($publique ? get_permalink($publique) : home_url('/publique-conosco/')); ?>">Quero publicar meu livro</a>
            </div>

            <?php if (file_exists($arte_banner)) : ?>
                <img class="catalogo-banner__arte" src="<?php echo esc_url(get_template_directory_uri().'/assets/img/catalogo-banner.svg'); ?>" alt="" aria-hidden="true">
            <?php endif; ?>
        </aside>
    </div>

    <?php if ($categorias_destaque && ! is_wp_error($categorias_destaque)) : ?>
        <section class="catalogo-destaques">
            <h2>Categorias em destaque</h2>

            <ul>
                <?php foreach ($categorias_destaque as $categoria) : ?>
                    <?php $ativa = in_array($categoria->slug, conexao_filtro_selecionado('cat'), true); ?>
                    <li>
                        <a class="catalogo-destaque<?php echo $ativa ? ' catalogo-destaque--ativa' : ''; ?>"
                           href="<?php echo esc_url(conexao_link_catalogo(['cat' => [$categoria->slug]])); ?>">
                            <?php
                            $svg = conexao_svg_categoria($categoria->slug);
                            echo $svg ?: conexao_icon(conexao_icone_categoria($categoria->slug), 34); // phpcs:ignore WordPress.Security.EscapeOutput
                            ?>
                            <span><?php echo esc_html($categoria->name); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <div class="catalogo">
        <?php get_template_part('template-parts/catalogo/filtros'); ?>

        <div class="catalogo__conteudo">
            <div class="catalogo__barra">
                <form class="busca busca--catalogo" role="search" method="get" action="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">
                    <label class="tela-leitor" for="busca-catalogo">Buscar no catálogo</label>
                    <input type="search" id="busca-catalogo" name="s" value="<?php echo esc_attr($busca); ?>" placeholder="Buscar no catálogo...">
                    <input type="hidden" name="post_type" value="product">
                    <button type="submit" aria-label="Buscar"><?php conexao_the_icon('lupa', 18); ?></button>
                </form>

                <form class="catalogo__ordem" method="get" action="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">
                    <label class="tela-leitor" for="catalogo-ordem">Ordenar por</label>
                    <select class="campo__entrada campo__entrada--select" id="catalogo-ordem" name="orderby" onchange="this.form.submit()">
                        <?php foreach (conexao_ordenacoes() as $valor => $rotulo) : ?>
                            <option value="<?php echo esc_attr($valor); ?>" <?php selected($ordem_atual, $valor); ?>><?php echo esc_html($rotulo); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <?php foreach ($_GET as $chave => $valor) : // phpcs:ignore WordPress.Security.NonceVerification ?>
                        <?php if ($chave === 'orderby' || $chave === 'paged') { continue; } ?>
                        <?php foreach ((array) $valor as $item) : ?>
                            <input type="hidden" name="<?php echo esc_attr(is_array($valor) ? $chave.'[]' : $chave); ?>" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($item))); ?>">
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </form>

                <div class="catalogo__visao" role="group" aria-label="Modo de exibição">
                    <a class="catalogo__visao-botao<?php echo $visao === 'grade' ? ' catalogo__visao-botao--ativa' : ''; ?>"
                       href="<?php echo esc_url(conexao_link_catalogo(['visao' => null])); ?>" aria-label="Ver em grade">
                        <?php conexao_the_icon('grade', 18); ?>
                    </a>
                    <a class="catalogo__visao-botao<?php echo $visao === 'lista' ? ' catalogo__visao-botao--ativa' : ''; ?>"
                       href="<?php echo esc_url(conexao_link_catalogo(['visao' => 'lista'])); ?>" aria-label="Ver em lista">
                        <?php conexao_the_icon('lista', 18); ?>
                    </a>
                </div>
            </div>

            <?php if (woocommerce_product_loop() && have_posts()) : ?>
                <div class="catalogo__grade catalogo__grade--<?php echo esc_attr($visao); ?>">
                    <?php
                    while (have_posts()) {
                        the_post();
                        get_template_part('template-parts/catalogo/card');
                    }
                    ?>
                </div>

                <?php
                $paginas = max(1, (int) $wp_query->max_num_pages);
                $atual = max(1, (int) get_query_var('paged'));
                ?>

                <?php if ($paginas > 1) : ?>
                    <nav class="navigation pagination" aria-label="Navegação do catálogo">
                        <div class="nav-links">
                            <?php if ($atual > 1) : ?>
                                <a class="prev page-numbers" href="<?php echo esc_url(get_pagenum_link($atual - 1)); ?>">Anterior</a>
                            <?php else : ?>
                                <span class="prev page-numbers desativado" aria-hidden="true">Anterior</span>
                            <?php endif; ?>

                            <?php
                            echo wp_kses_post((string) paginate_links([
                                'mid_size' => 4,
                                'prev_next' => false,
                                'type' => 'plain',
                                'total' => $paginas,
                                'current' => $atual,
                            ]));
                            ?>

                            <?php if ($atual < $paginas) : ?>
                                <a class="next page-numbers" href="<?php echo esc_url(get_pagenum_link($atual + 1)); ?>">Próxima</a>
                            <?php else : ?>
                                <span class="next page-numbers desativado" aria-hidden="true">Próxima</span>
                            <?php endif; ?>
                        </div>
                    </nav>
                <?php endif; ?>

                <p class="catalogo__contador"><?php echo esc_html(conexao_resumo_resultados()); ?></p>
            <?php else : ?>
                <p class="vazio">Nenhum livro encontrado com esses filtros.
                    <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Limpar filtros</a>.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
get_footer();
