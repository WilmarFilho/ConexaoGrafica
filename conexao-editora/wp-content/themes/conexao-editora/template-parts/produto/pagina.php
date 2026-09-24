<?php
/**
 * Página do livro: capa, ficha, caixa de compra, abas e sugestões.
 */

if (! defined('ABSPATH')) {
    exit;
}

get_header();

the_post();

global $product;

if (! $product instanceof WC_Product) {
    $product = wc_get_product(get_the_ID());
}

$id = $product->get_id();
$autores = conexao_autores($product);
$ficha = conexao_ficha_produto($product);
$avaliacoes = (int) $product->get_review_count();
$categorias = get_the_terms($id, 'product_cat');
$categoria = (! is_wp_error($categorias) && $categorias) ? $categorias[0] : null;

$parcelas = 6;
$preco = (float) $product->get_price();
$sugestoes = wc_get_products([
    'status' => 'publish',
    'limit' => 5,
    'exclude' => [$id],
    'category' => $categoria ? [$categoria->slug] : [],
    'orderby' => 'rand',
]);

if (count($sugestoes) < 5) {
    $sugestoes = wc_get_products(['status' => 'publish', 'limit' => 5, 'exclude' => [$id], 'orderby' => 'date']);
}
?>
<div class="container pagina pagina--produto">
    <?php
    conexao_trilha(get_the_title(), $categoria ? [[
        'url' => get_term_link($categoria),
        'texto' => $categoria->name,
    ]] : []);
    ?>

    <div class="produto">
        <figure class="produto__capa">
            <?php echo $product->get_image('woocommerce_single'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        </figure>

        <div class="produto__texto">
            <h1><?php the_title(); ?></h1>

            <?php if ($product->get_short_description()) : ?>
                <p class="produto__resumo"><?php echo wp_kses_post($product->get_short_description()); ?></p>
            <?php endif; ?>

            <div class="produto__meta">
                <?php if ($autores) : ?>
                    <span class="produto__autores">Autores: <?php echo esc_html($autores); ?></span>
                <?php endif; ?>

                <?php conexao_estrelas_produto($product); ?>

                <button class="compartilhar" type="button" data-compartilhar
                        data-url="<?php the_permalink(); ?>"
                        data-titulo="<?php echo esc_attr(get_the_title()); ?>"
                        aria-label="Compartilhar este livro">
                    <?php conexao_a_svg_produto('compartilhar', 18); ?>
                </button>
            </div>

            <?php if ($ficha) : ?>
                <ul class="produto__ficha">
                    <?php foreach ($ficha as $item) : ?>
                        <li>
                            <?php conexao_a_svg_produto($item['icone'], 26); ?>
                            <strong><?php echo esc_html($item['rotulo']); ?></strong>
                            <span><?php echo esc_html($item['valor']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="produto__compra">
            <div class="compra">
                <p class="compra__preco"><?php echo wp_kses_post($product->get_price_html()); ?></p>

                <?php if ($preco > 0) : ?>
                    <p class="compra__parcelas">ou <?php echo esc_html($parcelas); ?>x de <?php echo wp_kses_post(wc_price($preco / $parcelas)); ?> sem juros</p>
                <?php endif; ?>

                <?php
                // o seletor de formato só aparece quando o livro tem variações
                if ($product->is_type('variable')) {
                    woocommerce_template_single_add_to_cart();
                } else {
                    ?>
                    <form class="compra__form" method="post" enctype="multipart/form-data"
                          action="<?php echo esc_url($product->get_permalink()); ?>">
                        <?php if ($product->is_purchasable() && $product->is_in_stock()) : ?>
                            <div class="passo">
                                <button class="passo__botao" type="button" data-passo="-1" aria-label="Diminuir quantidade">−</button>
                                <label class="tela-leitor" for="compra-qtd">Quantidade</label>
                                <input class="passo__campo" type="number" id="compra-qtd" name="quantity" value="1" min="1" step="1" inputmode="numeric">
                                <button class="passo__botao" type="button" data-passo="1" aria-label="Aumentar quantidade">+</button>
                            </div>

                            <button class="btn btn--azul btn--bloco" type="submit" name="add-to-cart" value="<?php echo esc_attr($id); ?>">Adicionar ao carrinho</button>

                            <button class="btn btn--contorno btn--bloco" type="submit" name="conexao_comprar" value="1">Comprar agora</button>
                        <?php else : ?>
                            <p class="compra__indisponivel">Este título está indisponível no momento.</p>
                        <?php endif; ?>
                    </form>
                <?php } ?>

                <p class="compra__seguro"><?php conexao_the_icon('cadeado', 18); ?> Compra segura</p>
            </div>

            <div class="frete" data-frete data-produto="<?php echo esc_attr($id); ?>"
                 data-nonce="<?php echo esc_attr(wp_create_nonce('conexao_frete')); ?>">
                <p class="frete__titulo"><?php conexao_a_svg_produto('pin', 16); ?> Frete e prazo</p>

                <div class="frete__linha">
                    <label class="tela-leitor" for="frete-cep">CEP</label>
                    <input type="text" id="frete-cep" inputmode="numeric" placeholder="Digite seu CEP" maxlength="9" data-frete-cep>
                    <button class="btn btn--azul btn--pequeno" type="button" data-frete-calcular>Calcular</button>
                </div>

                <div class="frete__resposta" data-frete-resposta role="status"></div>
            </div>
        </div>
    </div>

    <?php get_template_part('template-parts/produto/abas'); ?>

    <?php if ($sugestoes) : ?>
        <section class="produto-sugestoes">
            <?php conexao_secao_titulo('Você também pode gostar de', class_exists('WooCommerce') ? wc_get_page_permalink('shop') : ''); ?>

            <div class="produto-sugestoes__grade">
                <?php foreach ($sugestoes as $sugestao) : ?>
                    <?php $outros_autores = conexao_autores($sugestao); ?>
                    <article class="card-catalogo card-catalogo--limpo">
                        <a class="card-catalogo__capa" href="<?php echo esc_url($sugestao->get_permalink()); ?>" tabindex="-1" aria-hidden="true">
                            <?php echo $sugestao->get_image('woocommerce_thumbnail'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                        </a>

                        <h3 class="card-catalogo__titulo"><a href="<?php echo esc_url($sugestao->get_permalink()); ?>"><?php echo esc_html($sugestao->get_name()); ?></a></h3>

                        <?php if ($outros_autores) : ?>
                            <p class="card-catalogo__autores">Autores: <?php echo esc_html($outros_autores); ?></p>
                        <?php endif; ?>

                        <p class="card-catalogo__preco"><?php echo wp_kses_post($sugestao->get_price_html()); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
<?php
get_footer();
