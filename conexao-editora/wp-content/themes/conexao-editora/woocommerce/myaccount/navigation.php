<?php
/**
 * Lateral da Minha conta: cartão do leitor (iniciais, nome, e-mail) e o menu
 * com ícones. Os itens vêm do WooCommerce (inclusive os de outros plugins).
 */

if (! defined('ABSPATH')) {
    exit;
}

$usuario = wp_get_current_user();
$nome = trim($usuario->display_name) ?: $usuario->user_login;
$partes = preg_split('/\s+/', remove_accents($nome)) ?: [];
$iniciais = strtoupper(mb_substr($partes[0] ?? 'L', 0, 1).(count($partes) > 1 ? mb_substr(end($partes), 0, 1) : ''));

$icones = [
    'dashboard' => 'grade',
    'orders' => 'carrinho',
    'biblioteca' => 'livro',
    'downloads' => 'livro',
    'edit-address' => 'pin',
    'payment-methods' => 'cartao',
    'carteira' => 'cartao',
    'edit-account' => 'usuario',
    'customer-logout' => 'seta-direita',
];

do_action('woocommerce_before_account_navigation');
?>
<aside class="minha-conta__lateral">
    <div class="minha-conta__perfil">
        <span class="minha-conta__iniciais" aria-hidden="true"><?php echo esc_html($iniciais); ?></span>
        <p class="minha-conta__nome"><?php echo esc_html($nome); ?></p>
        <p class="minha-conta__email"><?php echo esc_html($usuario->user_email); ?></p>
    </div>

    <nav class="minha-conta__menu woocommerce-MyAccount-navigation" aria-label="Páginas da conta">
        <ul>
            <?php foreach (wc_get_account_menu_items() as $endpoint => $rotulo) : ?>
                <?php $ativo = wc_is_current_account_menu_item($endpoint); ?>
                <li class="<?php echo esc_attr(wc_get_account_menu_item_classes($endpoint)); ?>">
                    <a href="<?php echo esc_url(wc_get_account_endpoint_url($endpoint)); ?>"<?php echo $ativo ? ' aria-current="page"' : ''; ?>>
                        <?php echo conexao_icon($icones[$endpoint] ?? 'check', 20); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                        <span><?php echo esc_html($rotulo); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</aside>
<?php do_action('woocommerce_after_account_navigation'); ?>
