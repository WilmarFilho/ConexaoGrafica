<?php
/**
 * Peças que se repetem pelo tema.
 */

if (! defined('ABSPATH')) {
    exit;
}

require_once get_template_directory().'/inc/post-types.php';

/**
 * Versão de um arquivo do tema pela data de gravação: editou o CSS, salvou,
 * atualizou a página e a mudança aparece, sem limpar cache.
 */
function conexao_versao_arquivo(string $relativo): string
{
    $caminho = get_template_directory().$relativo;

    return file_exists($caminho) ? (string) filemtime($caminho) : CONEXAO_VERSION;
}

/** Marca no cabeçalho e no rodapé. */
function conexao_logo(string $variacao = 'topo'): void
{
    $arquivo = $variacao === 'rodape' ? 'logo-rodape.png' : 'logo.png';
    $caminho = get_template_directory().'/assets/img/'.$arquivo;

    if (has_custom_logo() && $variacao === 'topo') {
        the_custom_logo();

        return;
    }

    printf('<a class="marca marca--%1$s" href="%2$s" rel="home">', esc_attr($variacao), esc_url(home_url('/')));

    if (file_exists($caminho)) {
        $medidas = @getimagesize($caminho);
        $url = add_query_arg('v', filemtime($caminho), get_template_directory_uri().'/assets/img/'.$arquivo);

        printf(
            '<img src="%s" alt="%s"%s>',
            esc_url($url),
            esc_attr(get_bloginfo('name')),
            $medidas ? sprintf(' width="%d" height="%d"', $medidas[0], $medidas[1]) : ''
        );
    } else {
        // Sem o logo oficial ainda; o nome segura o lugar dele.
        printf('<span class="marca__texto">%s</span>', esc_html(get_bloginfo('name')));
    }

    echo '</a>';
}

/** Caminho curto no topo da página: Home / página atual. */
function conexao_trilha(?string $atual = null): void
{
    if ($atual === null) {
        if (function_exists('is_account_page') && is_account_page()) {
            $atual = is_user_logged_in() ? 'Minha conta' : 'Login | Cadastro';
        } elseif (is_singular() || is_page()) {
            $atual = get_the_title();
        } else {
            $atual = wp_strip_all_tags(get_the_archive_title());
        }
    }

    printf(
        '<nav class="trilha" aria-label="Você está em"><a href="%s">Home</a><span aria-hidden="true">/</span><span>%s</span></nav>',
        esc_url(home_url('/')),
        esc_html($atual)
    );
}

/** Tempo de leitura em minutos, a 200 palavras por minuto. */
function conexao_tempo_leitura(int $post_id): int
{
    $palavras = str_word_count(wp_strip_all_tags((string) get_post_field('post_content', $post_id)));

    return max(1, (int) ceil($palavras / 200));
}

/** Botão do carrinho no cabeçalho; também é usado para atualizá-lo por AJAX. */
function conexao_cart_button(): void
{
    $itens = 0;
    $url = home_url('/carrinho/');

    if (function_exists('WC') && WC()->cart) {
        $itens = WC()->cart->get_cart_contents_count();
        $url = wc_get_cart_url();
    }

    printf(
        '<a class="hdr-acao hdr-acao--carrinho" href="%1$s"><span class="hdr-acao__icone">%2$s</span><span class="hdr-acao__texto"><b>Meu carrinho</b><span>%3$s</span></span></a>',
        esc_url($url),
        conexao_icon('carrinho', 22), // phpcs:ignore WordPress.Security.EscapeOutput
        esc_html($itens === 1 ? '1 item' : $itens.' itens')
    );
}

/** Cabeçalho de seção: título, régua e botão "ver todos". */
function conexao_secao_titulo(string $titulo, string $url = '', string $rotulo = 'Ver todos'): void
{
    echo '<div class="secao-topo">';
    printf('<h2 class="secao-titulo">%s</h2>', esc_html($titulo));
    echo '<span class="secao-regua" aria-hidden="true"></span>';

    if ($url) {
        printf('<a class="btn btn--contorno btn--pequeno" href="%s">%s</a>', esc_url($url), esc_html($rotulo));
    }

    echo '</div>';
}

/** Lista de autores de um livro, já formatada. */
function conexao_autores(WC_Product $produto): string
{
    $termos = get_the_terms($produto->get_id(), 'autor');

    if (! $termos || is_wp_error($termos)) {
        return '';
    }

    return implode(', ', wp_list_pluck($termos, 'name'));
}

/** Cartão de livro usado na home e nas listagens. */
function conexao_card_produto(WC_Product $produto, bool $com_preco_antigo = true): void
{
    $autores = conexao_autores($produto);
    $imagem = $produto->get_image('woocommerce_thumbnail', ['class' => 'card-livro__capa']);
    ?>
    <article class="card-livro">
        <a class="card-livro__imagem" href="<?php echo esc_url($produto->get_permalink()); ?>" tabindex="-1" aria-hidden="true">
            <?php echo $imagem; // phpcs:ignore WordPress.Security.EscapeOutput ?>
        </a>
        <h3 class="card-livro__titulo">
            <a href="<?php echo esc_url($produto->get_permalink()); ?>"><?php echo esc_html($produto->get_name()); ?></a>
        </h3>
        <?php if ($autores) : ?>
            <p class="card-livro__autores">Autores: <?php echo esc_html($autores); ?></p>
        <?php endif; ?>
        <p class="card-livro__preco">
            <?php echo wp_kses_post($produto->get_price_html()); ?>
        </p>
    </article>
    <?php
}

/** Cartão de post, com a legenda sobre a foto. */
function conexao_card_post(WP_Post $post_item, bool $grande = false): void
{
    $categorias = get_the_category($post_item->ID);
    $categoria = $categorias ? $categorias[0]->name : '';
    ?>
    <article class="card-post<?php echo $grande ? ' card-post--grande' : ''; ?>">
        <a class="card-post__foto" href="<?php echo esc_url(get_permalink($post_item)); ?>" tabindex="-1" aria-hidden="true">
            <?php
            if (has_post_thumbnail($post_item)) {
                echo get_the_post_thumbnail($post_item, $grande ? 'large' : 'medium_large', ['loading' => 'lazy']);
            }
            ?>
        </a>

        <p class="card-post__data"><?php conexao_the_icon('calendario', 16); ?> <?php echo esc_html(get_the_date('j \d\e F, Y', $post_item)); ?></p>

        <div class="card-post__legenda">
            <?php if ($categoria) : ?>
                <span class="card-post__categoria"><?php echo esc_html($categoria); ?></span>
            <?php endif; ?>
            <h3><a href="<?php echo esc_url(get_permalink($post_item)); ?>"><?php echo esc_html(get_the_title($post_item)); ?></a></h3>
        </div>

        <a class="card-post__mais" href="<?php echo esc_url(get_permalink($post_item)); ?>" aria-label="<?php echo esc_attr('Ler: '.get_the_title($post_item)); ?>">
            <?php conexao_the_icon('mais', 20); ?>
        </a>
    </article>
    <?php
}

/** Produtos para as vitrines da home. */
function conexao_produtos(string $tipo, int $quantidade = 5): array
{
    $args = [
        'status' => 'publish',
        'limit' => $quantidade,
        'return' => 'objects',
    ];

    if ($tipo === 'mais-vendidos') {
        $args['orderby'] = 'popularity';
    } elseif ($tipo === 'destaque') {
        // a editora define a ordem pelo campo de ordenação do produto
        $args['featured'] = true;
        $args['orderby'] = 'menu_order';
        $args['order'] = 'ASC';
    } else {
        $args['orderby'] = 'date';
        $args['order'] = 'DESC';
    }

    if (! function_exists('wc_get_products')) {
        return [];
    }

    $produtos = wc_get_products($args);

    // Poucos destaques cadastrados: a vitrine completa com os mais recentes,
    // para o filtro por categoria ter o que mostrar.
    if ($tipo === 'destaque' && count($produtos) < $quantidade) {
        unset($args['featured']);
        $args['orderby'] = 'date';
        $args['order'] = 'DESC';
        $args['exclude'] = array_map(fn (WC_Product $p) => $p->get_id(), $produtos);
        $args['limit'] = $quantidade - count($produtos);

        $produtos = array_merge($produtos, wc_get_products($args));
    }

    return $produtos;
}

/** Estrelas da avaliação, no formato do layout. */
function conexao_estrelas(WC_Product $produto): void
{
    $nota = (float) $produto->get_average_rating();
    $quantidade = (int) $produto->get_rating_count();

    if ($quantidade < 1) {
        return;
    }

    echo '<span class="estrelas" role="img" aria-label="'.esc_attr(sprintf('%s de 5 estrelas', number_format_i18n($nota, 1))).'">';
    for ($i = 1; $i <= 5; $i++) {
        printf(
            '<span class="estrela%s">★</span>',
            $i <= round($nota) ? ' estrela--cheia' : ''
        );
    }
    echo '</span>';

    printf(
        '<span class="avaliacoes">%s</span>',
        esc_html(sprintf(_n('%s Avaliação', '%s Avaliações', $quantidade, 'conexao'), number_format_i18n($quantidade)))
    );
}
