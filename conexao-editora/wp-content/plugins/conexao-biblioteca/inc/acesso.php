<?php
/**
 * Quem pode ler o quê. O direito nasce de um pedido pago na loja ou de uma
 * concessão gravada na tabela de acessos (cortesia, outro canal, migração).
 * O filtro no fim deixa o Hub Editora acrescentar compras de outros canais.
 */

if (! defined('ABSPATH')) {
    exit;
}

/** Estados de pedido que liberam a leitura. */
function conexao_biblioteca_estados_pagos(): array
{
    return (array) apply_filters('conexao_biblioteca_estados_pagos', ['processing', 'completed']);
}

/**
 * Livros que o usuário pode ler.
 *
 * @return array<int, array{origem: string, referencia: string, data: string}> por ID de produto
 */
function conexao_biblioteca_livros_do_usuario(int $usuario_id): array
{
    static $memoria = [];

    if (! $usuario_id) {
        return [];
    }

    if (isset($memoria[$usuario_id])) {
        return $memoria[$usuario_id];
    }

    $livros = [];

    $libera = static function (int $produto_id, string $origem, string $referencia, string $data) use (&$livros): void {
        $produto_id = conexao_biblioteca_produto_base($produto_id);

        if ($produto_id && ! isset($livros[$produto_id])) {
            $livros[$produto_id] = ['origem' => $origem, 'referencia' => $referencia, 'data' => $data];
        }
    };

    if (function_exists('wc_get_orders')) {
        $pedidos = wc_get_orders([
            'customer_id' => $usuario_id,
            'status' => conexao_biblioteca_estados_pagos(),
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        foreach ($pedidos as $pedido) {
            $data = $pedido->get_date_created() ? $pedido->get_date_created()->date('Y-m-d H:i:s') : '';

            foreach ($pedido->get_items() as $item) {
                $produto_id = (int) $item->get_product_id();
                $variacao_id = (int) $item->get_variation_id();

                if (! conexao_biblioteca_item_digital($produto_id, $variacao_id)) {
                    continue;
                }

                $libera($produto_id, 'pedido', (string) $pedido->get_order_number(), $data);

                foreach (conexao_biblioteca_combo($produto_id) as $incluido) {
                    $libera($incluido, 'pedido', (string) $pedido->get_order_number(), $data);
                }
            }
        }
    }

    global $wpdb;

    $concedidos = $wpdb->get_results($wpdb->prepare(
        "SELECT produto_id, origem, referencia, criado_em FROM {$wpdb->prefix}conexao_biblioteca_acessos
         WHERE usuario_id = %d AND (expira_em IS NULL OR expira_em > %s)",
        $usuario_id,
        current_time('mysql')
    ));

    foreach ((array) $concedidos as $linha) {
        $libera((int) $linha->produto_id, (string) $linha->origem, (string) $linha->referencia, (string) $linha->criado_em);

        foreach (conexao_biblioteca_combo((int) $linha->produto_id) as $incluido) {
            $libera($incluido, (string) $linha->origem, (string) $linha->referencia, (string) $linha->criado_em);
        }
    }

    /**
     * Ponto de entrada para outros canais (Hub Editora, Amazon, landing).
     *
     * @param array $livros     mapa produto => origem
     * @param int   $usuario_id
     */
    $livros = (array) apply_filters('conexao_biblioteca_livros_do_usuario', $livros, $usuario_id);

    return $memoria[$usuario_id] = $livros;
}

/** O item comprado dá direito à leitura? */
function conexao_biblioteca_item_digital(int $produto_id, int $variacao_id): bool
{
    if ($variacao_id) {
        return conexao_biblioteca_variacao_digital($variacao_id);
    }

    // coleção vendida como produto simples: libera os livros que ela reúne
    if (conexao_biblioteca_combo($produto_id)) {
        return true;
    }

    return get_post_meta($produto_id, '_virtual', true) === 'yes'
        || get_post_meta($produto_id, '_downloadable', true) === 'yes';
}

function conexao_biblioteca_pode_ler(int $usuario_id, int $produto_id): bool
{
    if (! $usuario_id) {
        return false;
    }

    // a equipe da editora confere qualquer livro sem precisar comprar
    if (user_can($usuario_id, 'manage_woocommerce')) {
        return true;
    }

    $produto_id = conexao_biblioteca_produto_base($produto_id);

    return isset(conexao_biblioteca_livros_do_usuario($usuario_id)[$produto_id]);
}

/** Concede um livro fora de pedido. Devolve false se já existia. */
function conexao_biblioteca_concede(int $usuario_id, int $produto_id, string $origem = 'manual', string $referencia = '', ?string $expira_em = null): bool
{
    global $wpdb;

    $existe = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}conexao_biblioteca_acessos
         WHERE usuario_id = %d AND produto_id = %d AND origem = %s AND referencia = %s",
        $usuario_id,
        $produto_id,
        $origem,
        $referencia
    ));

    if ($existe) {
        return false;
    }

    return (bool) $wpdb->insert("{$wpdb->prefix}conexao_biblioteca_acessos", [
        'usuario_id' => $usuario_id,
        'produto_id' => conexao_biblioteca_produto_base($produto_id),
        'origem' => $origem,
        'referencia' => $referencia,
        'criado_em' => current_time('mysql'),
        'expira_em' => $expira_em,
    ]);
}
