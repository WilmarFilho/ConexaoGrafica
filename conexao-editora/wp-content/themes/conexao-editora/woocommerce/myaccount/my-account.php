<?php
/**
 * Minha conta (substitui o template do WooCommerce): perfil e menu à esquerda,
 * o conteúdo da aba num cartão à direita, no visual da loja.
 */

if (! defined('ABSPATH')) {
    exit;
}

$usuario = wp_get_current_user();
$atual = 'dashboard';

foreach (array_keys(wc_get_account_menu_items()) as $endpoint) {
    if (wc_is_current_account_menu_item($endpoint)) {
        $atual = $endpoint;
    }
}

if (is_wc_endpoint_url('view-order')) {
    $atual = 'view-order';
}

$titulos = wc_get_account_menu_items() + ['view-order' => 'Pedido'];
$titulo = $atual === 'dashboard' ? 'Olá, '.(explode(' ', trim($usuario->display_name))[0] ?: 'leitor') : ($titulos[$atual] ?? 'Minha conta');
?>
<div class="minha-conta minha-conta--<?php echo esc_attr($atual); ?>">
    <?php do_action('woocommerce_account_navigation'); ?>

    <section class="minha-conta__conteudo woocommerce-MyAccount-content" aria-labelledby="minha-conta-titulo">
        <h1 class="minha-conta__titulo" id="minha-conta-titulo"><?php echo esc_html($titulo); ?></h1>
        <?php do_action('woocommerce_account_content'); ?>
    </section>
</div>
