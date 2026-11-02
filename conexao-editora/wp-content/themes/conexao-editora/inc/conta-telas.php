<?php
/**
 * Telas de "Minha conta" no visual da loja: lista de pedidos (situação em
 * pílula, coluna Entrega que entende livro digital), detalhes da conta sem o
 * CPF repetido e textos em português onde o WooCommerce ainda vem em inglês.
 */

if (! defined('ABSPATH')) {
    exit;
}

/** O pedido só tem itens digitais (nada para postar)? */
function conexao_conta_pedido_digital(WC_Order $pedido): bool
{
    foreach ($pedido->get_items() as $item) {
        $produto = $item->get_product();

        if ($produto && ! $produto->is_virtual() && ! $produto->is_downloadable()) {
            return false;
        }
    }

    return count($pedido->get_items()) > 0;
}

function conexao_conta_situacao(WC_Order $pedido): string
{
    return sprintf(
        '<span class="minha-conta__situacao minha-conta__situacao--%s">%s</span>',
        esc_attr($pedido->get_status()),
        esc_html(wc_get_order_status_name($pedido->get_status()))
    );
}

// ---------------------------------------------------------------- lista de pedidos

// "Rastreio" do Melhor Envio vira "Entrega": livro digital não espera postagem
add_filter('woocommerce_account_orders_columns', function (array $colunas): array {
    $novas = [];

    foreach ($colunas as $chave => $nome) {
        if ($chave === 'tracking') {
            continue;
        }

        $novas[$chave] = $nome;

        if ($chave === 'order-status') {
            $novas['entrega'] = 'Entrega';
        }
    }

    $novas['order-actions'] = '';

    return $novas;
}, 99);

add_action('woocommerce_my_account_my_orders_column_order-status', function (WC_Order $pedido): void {
    echo conexao_conta_situacao($pedido); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
});

add_action('woocommerce_my_account_my_orders_column_entrega', function (WC_Order $pedido): void {
    if (conexao_conta_pedido_digital($pedido)) {
        $pago = function_exists('conexao_biblioteca_estados_pagos') && in_array($pedido->get_status(), conexao_biblioteca_estados_pagos(), true);

        echo $pago
            ? '<a class="minha-conta__entrega-digital" href="'.esc_url(wc_get_account_endpoint_url('biblioteca')).'">Na biblioteca</a>'
            : '<span class="minha-conta__entrega-digital">Livro digital</span>';

        return;
    }

    if (has_action('woocommerce_my_account_my_orders_column_tracking')) {
        do_action('woocommerce_my_account_my_orders_column_tracking', $pedido); // rastreio do Melhor Envio

        return;
    }

    echo '<span class="minha-conta__fraco">—</span>';
});

add_action('woocommerce_my_account_my_orders_column_order-total', function (WC_Order $pedido): void {
    $itens = $pedido->get_item_count() - $pedido->get_item_count_refunded();

    printf(
        '<strong>%s</strong><span class="minha-conta__fraco">%s</span>',
        wp_kses_post($pedido->get_formatted_order_total()),
        esc_html(sprintf(_n('%d item', '%d itens', $itens, 'conexao-editora'), $itens))
    );
});

add_filter('woocommerce_my_account_my_orders_actions', function (array $acoes): array {
    if (isset($acoes['view'])) {
        $acoes['view']['name'] = 'Ver pedido';
    }

    return $acoes;
}, 20);

// aviso do WooCommerce 11 que ainda não tem tradução
add_filter('gettext_woocommerce', function (string $traducao, string $texto): string {
    return match ($texto) {
        'Confirm your email address to check for past orders and link them to your account.' => 'Confirme o seu e-mail para encontrarmos pedidos antigos feitos com ele e juntá-los à sua conta.',
        'Confirm email address' => 'Confirmar e-mail',
        // botões dos cartões de endereço: o título do cartão já diz qual é
        'Edit %s' => is_account_page() ? 'Editar' : $traducao,
        'Add %s' => is_account_page() ? 'Adicionar' : $traducao,
        default => $traducao,
    };
}, 10, 2);

// ---------------------------------------------------------------- detalhes da conta

/*
 * O CPF do checkout em blocos (inc/cpf.php) é um campo de "contato", e o
 * WooCommerce repete esses campos no formulário da conta, vazio. O tema já
 * mostra o CPF (preenchido, com as mesmas regras): tira a cópia do WooCommerce.
 */
add_action('wp_loaded', function (): void {
    global $wp_filter;

    $alvos = [
        'woocommerce_edit_account_form_fields' => 'edit_account_form_fields',
        'woocommerce_save_account_details' => 'save_account_form_fields',
    ];

    foreach ($alvos as $gancho => $metodo) {
        if (! isset($wp_filter[$gancho])) {
            continue;
        }

        foreach ($wp_filter[$gancho]->callbacks as $prioridade => $funcoes) {
            foreach ($funcoes as $funcao) {
                $f = $funcao['function'];

                if (is_array($f) && is_object($f[0]) && $f[1] === $metodo
                    && $f[0] instanceof \Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFieldsFrontend) {
                    remove_filter($gancho, $f, $prioridade);
                }
            }
        }
    }
}, 20);
