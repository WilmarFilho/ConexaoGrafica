<?php
/**
 * Painel da Minha conta: resumo em cartões (biblioteca, pedidos, endereço,
 * dados) com atalhos. Substitui o texto padrão do WooCommerce.
 */

if (! defined('ABSPATH')) {
    exit;
}

$usuario = wp_get_current_user();
$pedidos = wc_get_orders(['customer_id' => $usuario->ID, 'limit' => 3, 'orderby' => 'date', 'order' => 'DESC']);
$total_pedidos = wc_get_customer_order_count($usuario->ID);
$livros = function_exists('conexao_biblioteca_livros_do_usuario') ? conexao_biblioteca_livros_do_usuario($usuario->ID) : [];
$tem_biblioteca = array_key_exists('biblioteca', wc_get_account_menu_items());

$endereco = array_filter([
    $usuario->shipping_address_1 ?: $usuario->billing_address_1,
    $usuario->shipping_city ?: $usuario->billing_city,
    $usuario->shipping_state ?: $usuario->billing_state,
]);
?>
<p class="minha-conta__boas-vindas">Aqui você acompanha seus pedidos, lê seus livros digitais e cuida dos seus dados.</p>

<div class="minha-conta__cartoes">
    <?php if ($tem_biblioteca) : ?>
        <article class="minha-conta__cartao minha-conta__cartao--destaque">
            <span class="minha-conta__cartao-icone"><?php echo conexao_icon('livro', 26); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
            <h2>Minha biblioteca</h2>
            <?php if ($livros) : ?>
                <p><?php echo esc_html(sprintf(_n('%d livro digital para ler', '%d livros digitais para ler', count($livros), 'conexao'), count($livros))); ?></p>
                <div class="minha-conta__capas" aria-hidden="true">
                    <?php foreach (array_slice(array_keys($livros), 0, 4) as $produto_id) : ?>
                        <?php $produto = wc_get_product($produto_id); ?>
                        <?php if ($produto) : ?>
                            <a href="<?php echo esc_url(conexao_biblioteca_url_leitor($produto_id)); ?>" tabindex="-1"><?php echo $produto->get_image('woocommerce_thumbnail'); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p>Seus e-books aparecem aqui assim que a compra for confirmada.</p>
            <?php endif; ?>
            <a class="btn btn--azul btn--pequeno" href="<?php echo esc_url(wc_get_account_endpoint_url('biblioteca')); ?>"><?php echo $livros ? 'Abrir a biblioteca' : 'Ver e-books na loja'; ?></a>
        </article>
    <?php endif; ?>

    <article class="minha-conta__cartao">
        <span class="minha-conta__cartao-icone"><?php echo conexao_icon('carrinho', 26); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <h2>Pedidos</h2>
        <?php if ($pedidos) : ?>
            <ul class="minha-conta__ultimos">
                <?php foreach ($pedidos as $pedido) : ?>
                    <li>
                        <a href="<?php echo esc_url($pedido->get_view_order_url()); ?>">#<?php echo esc_html($pedido->get_order_number()); ?></a>
                        <span class="minha-conta__situacao minha-conta__situacao--<?php echo esc_attr($pedido->get_status()); ?>"><?php echo esc_html(wc_get_order_status_name($pedido->get_status())); ?></span>
                        <time datetime="<?php echo esc_attr($pedido->get_date_created() ? $pedido->get_date_created()->date('c') : ''); ?>"><?php echo esc_html($pedido->get_date_created() ? $pedido->get_date_created()->date_i18n('d/m/Y') : ''); ?></time>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a class="minha-conta__link" href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>">Ver todos os <?php echo (int) $total_pedidos; ?> pedidos</a>
        <?php else : ?>
            <p>Você ainda não fez nenhum pedido.</p>
            <a class="minha-conta__link" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">Conhecer o catálogo</a>
        <?php endif; ?>
    </article>

    <article class="minha-conta__cartao">
        <span class="minha-conta__cartao-icone"><?php echo conexao_icon('pin', 26); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <h2>Endereço de entrega</h2>
        <?php if ($endereco) : ?>
            <p><?php echo esc_html(implode(', ', $endereco)); ?></p>
        <?php else : ?>
            <p>Nenhum endereço salvo. Cadastre para agilizar a compra.</p>
        <?php endif; ?>
        <a class="minha-conta__link" href="<?php echo esc_url(wc_get_account_endpoint_url('edit-address')); ?>"><?php echo $endereco ? 'Alterar endereços' : 'Cadastrar endereço'; ?></a>
    </article>

    <article class="minha-conta__cartao">
        <span class="minha-conta__cartao-icone"><?php echo conexao_icon('usuario', 26); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
        <h2>Dados da conta</h2>
        <p><?php echo esc_html($usuario->user_email); ?></p>
        <a class="minha-conta__link" href="<?php echo esc_url(wc_get_account_endpoint_url('edit-account')); ?>">Alterar nome, e-mail ou senha</a>
    </article>
</div>

<?php
do_action('woocommerce_account_dashboard');
do_action('woocommerce_before_my_account');
do_action('woocommerce_after_my_account');
