<?php
/**
 * "Minha biblioteca" dentro da conta do cliente: os livros que ele pode ler.
 */

if (! defined('ABSPATH')) {
    exit;
}

function conexao_biblioteca_rota_conta(): void
{
    add_rewrite_endpoint('biblioteca', EP_ROOT | EP_PAGES);
}
add_action('init', 'conexao_biblioteca_rota_conta');

add_filter('woocommerce_get_query_vars', function (array $vars): array {
    $vars['biblioteca'] = 'biblioteca';

    return $vars;
});

// logo depois de "Pedidos", que é onde o leitor procura
add_filter('woocommerce_account_menu_items', function (array $itens): array {
    $novo = [];

    foreach ($itens as $chave => $rotulo) {
        $novo[$chave] = $rotulo;

        if ($chave === 'orders') {
            $novo['biblioteca'] = 'Minha biblioteca';
        }
    }

    if (! isset($novo['biblioteca'])) {
        $novo['biblioteca'] = 'Minha biblioteca';
    }

    // os downloads do WooCommerce não são usados: a leitura é pelo leitor
    unset($novo['downloads']);

    return $novo;
}, 20);

add_filter('woocommerce_endpoint_biblioteca_title', fn () => 'Minha biblioteca');

add_action('wp_enqueue_scripts', function (): void {
    if (function_exists('is_account_page') && is_account_page()) {
        wp_enqueue_style(
            'conexao-biblioteca-conta',
            CONEXAO_BIBLIOTECA_URL.'assets/conta.css',
            [],
            CONEXAO_BIBLIOTECA_VERSAO
        );
    }
});

add_action('woocommerce_account_biblioteca_endpoint', function (): void {
    $livros = conexao_biblioteca_livros_do_usuario(get_current_user_id());
    ?>
    <section class="biblioteca">
        <header class="biblioteca__topo">
            <h2>Minha biblioteca</h2>
            <p>Seus livros digitais ficam aqui. A leitura é feita no próprio site, no computador ou no celular.</p>
        </header>

        <?php if (! $livros) : ?>
            <p class="biblioteca__vazia">
                Você ainda não tem livros digitais.
                <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Conhecer o catálogo</a>
            </p>
        <?php else : ?>
            <ul class="biblioteca__grade">
                <?php foreach ($livros as $produto_id => $dados) : ?>
                    <?php
                    $produto = wc_get_product($produto_id);

                    if (! $produto) {
                        continue;
                    }

                    $livro = conexao_biblioteca_livro($produto_id);
                    ?>
                    <li class="biblioteca__livro">
                        <span class="biblioteca__capa">
                            <?php echo $produto->get_image('woocommerce_thumbnail'); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                        </span>

                        <h3><?php echo esc_html($produto->get_name()); ?></h3>

                        <?php if ($livro['modelo']) : ?>
                            <a class="btn btn--azul btn--bloco btn--pequeno" href="<?php echo esc_url(conexao_biblioteca_url_leitor($produto_id)); ?>">Ler agora</a>
                        <?php else : ?>
                            <span class="biblioteca__aviso">Em preparação. Avisaremos quando estiver pronto.</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <?php
});

/** No pedido concluído e no e-mail, o caminho para a leitura. */
add_action('woocommerce_order_details_after_order_table', function ($pedido): void {
    if (! $pedido || ! in_array($pedido->get_status(), conexao_biblioteca_estados_pagos(), true)) {
        return;
    }

    foreach ($pedido->get_items() as $item) {
        if (conexao_biblioteca_item_digital((int) $item->get_product_id(), (int) $item->get_variation_id())) {
            printf(
                '<p class="biblioteca__chamada">Este pedido tem livro digital. <a href="%s">Abrir minha biblioteca</a></p>',
                esc_url(wc_get_account_endpoint_url('biblioteca'))
            );

            return;
        }
    }
});
