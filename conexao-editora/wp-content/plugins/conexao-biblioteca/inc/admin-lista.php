<?php
/**
 * Produtos → Biblioteca digital: todos os livros com leitura digital, o modelo
 * de cada um e o envio do PDF na própria linha. É por aqui que a editora vai
 * trocando o flipbook pelo PDF, título a título.
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function (): void {
    add_submenu_page(
        'edit.php?post_type=product',
        'Biblioteca digital',
        'Biblioteca digital',
        'edit_products',
        'conexao-biblioteca',
        'conexao_biblioteca_tela_lista'
    );
});

/** Leitores por livro: clientes distintos com pedido pago do produto. */
function conexao_biblioteca_leitores_por_livro(): array
{
    global $wpdb;

    $estados = array_map(static fn ($e) => 'wc-'.$e, conexao_biblioteca_estados_pagos());
    $marcas = implode(',', array_fill(0, count($estados), '%s'));

    $linhas = $wpdb->get_results($wpdb->prepare(
        "SELECT l.product_id, COUNT(DISTINCT l.customer_id) AS leitores
         FROM {$wpdb->prefix}wc_order_product_lookup l
         JOIN {$wpdb->prefix}wc_order_stats s ON s.order_id = l.order_id
         WHERE s.status IN ($marcas)
         GROUP BY l.product_id",
        ...$estados
    ));

    $mapa = [];

    foreach ((array) $linhas as $linha) {
        $mapa[(int) $linha->product_id] = (int) $linha->leitores;
    }

    return $mapa;
}

function conexao_biblioteca_tela_lista(): void
{
    $filtro = isset($_GET['modelo']) ? sanitize_key(wp_unslash($_GET['modelo'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    $leitores = conexao_biblioteca_leitores_por_livro();

    $livros = [];
    $conta = ['pdf' => 0, 'flipbook' => 0, 'vazio' => 0];

    foreach (conexao_biblioteca_todos_os_livros() as $produto_id) {
        $livro = conexao_biblioteca_livro($produto_id);
        $chave = $livro['modelo'] ?: 'vazio';
        $conta[$chave]++;
        $livros[] = ['id' => $produto_id, 'livro' => $livro, 'chave' => $chave];
    }

    $base = admin_url('edit.php?post_type=product&page=conexao-biblioteca');
    $total = count($livros);
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Biblioteca digital</h1>
        <p>Os livros novos entram direto em PDF. Os do acervo antigo continuam no flipbook até o PDF ser enviado: a troca vale na hora, sem mexer no que o leitor já comprou.</p>

        <div class="cb-resumo">
            <div><strong><?php echo esc_html((string) $total); ?></strong> livros digitais</div>
            <div><strong><?php echo esc_html((string) $conta['pdf']); ?></strong> em PDF</div>
            <div><strong><?php echo esc_html((string) $conta['flipbook']); ?></strong> em flipbook</div>
            <div><strong><?php echo esc_html((string) $conta['vazio']); ?></strong> sem arquivo</div>
        </div>

        <ul class="subsubsub">
            <li><a href="<?php echo esc_url($base); ?>" class="<?php echo $filtro === '' ? 'current' : ''; ?>">Todos <span class="count">(<?php echo esc_html((string) $total); ?>)</span></a> |</li>
            <li><a href="<?php echo esc_url(add_query_arg('modelo', 'flipbook', $base)); ?>" class="<?php echo $filtro === 'flipbook' ? 'current' : ''; ?>">Flipbook <span class="count">(<?php echo esc_html((string) $conta['flipbook']); ?>)</span></a> |</li>
            <li><a href="<?php echo esc_url(add_query_arg('modelo', 'pdf', $base)); ?>" class="<?php echo $filtro === 'pdf' ? 'current' : ''; ?>">PDF <span class="count">(<?php echo esc_html((string) $conta['pdf']); ?>)</span></a> |</li>
            <li><a href="<?php echo esc_url(add_query_arg('modelo', 'vazio', $base)); ?>" class="<?php echo $filtro === 'vazio' ? 'current' : ''; ?>">Sem arquivo <span class="count">(<?php echo esc_html((string) $conta['vazio']); ?>)</span></a></li>
        </ul>

        <table class="wp-list-table widefat fixed striped cb-lista">
            <thead>
                <tr>
                    <th style="width:34%">Livro</th>
                    <th style="width:14%">Leitura hoje</th>
                    <th style="width:12%">Flipbook</th>
                    <th style="width:10%">Leitores</th>
                    <th>PDF</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($livros as $linha) : ?>
                    <?php
                    if ($filtro && $filtro !== $linha['chave']) {
                        continue;
                    }

                    $id = $linha['id'];
                    $pasta = (string) get_post_meta($id, CONEXAO_LIVRO_FLIPBOOK, true);
                    ?>
                    <tr>
                        <td>
                            <strong><a href="<?php echo esc_url(get_edit_post_link($id)); ?>"><?php echo esc_html(get_the_title($id)); ?></a></strong>
                            <div class="row-actions">
                                <span>#<?php echo esc_html((string) $id); ?></span>
                                <?php if ($linha['livro']['modelo']) : ?>
                                    | <a href="<?php echo esc_url(conexao_biblioteca_url_leitor($id)); ?>" target="_blank" rel="noopener">Abrir no leitor</a>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?php echo conexao_biblioteca_rotulo_modelo($linha['livro']['modelo']); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
                        <td><?php echo $pasta !== '' ? esc_html($pasta).($linha['livro']['flipbook'] ? '' : ' <em>(pasta ausente)</em>') : '—'; // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
                        <td><?php echo esc_html((string) ($leitores[$id] ?? 0)); ?></td>
                        <td><?php conexao_biblioteca_campo_envio($id); ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (! $livros) : ?>
                    <tr><td colspan="5">Nenhum livro digital cadastrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
