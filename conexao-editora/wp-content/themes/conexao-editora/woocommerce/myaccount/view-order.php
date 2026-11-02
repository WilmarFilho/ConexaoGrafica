<?php
/**
 * Pedido em "Minha conta" (substitui o template do WooCommerce): resumo em
 * faixa (data, situação, total, entrega), atualizações da loja e os detalhes.
 *
 * @version 10.6.0
 */

defined('ABSPATH') || exit;

$notes = $order->get_customer_order_notes();
$digital = conexao_conta_pedido_digital($order);

// e-book já é do leitor: "Comprar novamente" não faz sentido
if ($digital) {
    remove_action('woocommerce_order_details_after_order_table', 'woocommerce_order_again_button');
}
?>
<a class="minha-conta__voltar" href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>">
    <?php conexao_the_icon('seta-esquerda', 16); ?> Todos os pedidos
</a>

<dl class="minha-conta__resumo">
    <div>
        <dt>Feito em</dt>
        <dd><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></dd>
    </div>
    <div>
        <dt>Situação</dt>
        <dd><?php echo conexao_conta_situacao($order); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd>
    </div>
    <div>
        <dt>Total</dt>
        <dd><?php echo wp_kses_post($order->get_formatted_order_total()); ?></dd>
    </div>
    <div>
        <dt>Entrega</dt>
        <dd><?php echo esc_html($digital ? 'Livro digital' : ($order->get_shipping_method() ?: '—')); ?></dd>
    </div>
</dl>

<?php if ($notes) : ?>
    <h2>Atualizações do pedido</h2>
    <ol class="woocommerce-OrderUpdates commentlist notes minha-conta__notas">
        <?php foreach ($notes as $note) : ?>
            <li class="woocommerce-OrderUpdate comment note">
                <time><?php echo esc_html(wc_format_datetime(wc_string_to_datetime($note->comment_date))); ?></time>
                <div class="woocommerce-OrderUpdate-description description">
                    <?php echo wp_kses_post(wpautop(wptexturize($note->comment_content))); ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>

<?php do_action('woocommerce_view_order', $order_id); ?>
