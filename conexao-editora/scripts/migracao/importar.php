<?php
/**
 * Roda NO SITE NOVO: lê o pacote gerado pelo exportar.php e traz clientes,
 * pedidos, avaliações e cupons da Pubcon, além de ligar cada livro à pasta do
 * flipbook antigo.
 *
 *   wp eval-file importar.php                     simulação (não grava nada)
 *   CONEXAO_APLICAR=1 wp eval-file importar.php   para valer
 *
 * Pode rodar de novo: o que já veio é reconhecido pelo número de origem.
 * Nenhum e-mail sai e nenhum estoque é mexido durante a importação.
 */

if (! class_exists('WooCommerce')) {
    WP_CLI::error('WooCommerce não está ativo.');
}

if (! function_exists('conexao_biblioteca_livro')) {
    WP_CLI::error('Ative o plugin Conexão Biblioteca antes de importar.');
}

global $wpdb;

$aplicar = (bool) getenv('CONEXAO_APLICAR');
$arquivo = getenv('CONEXAO_PACOTE') ?: '/scripts/../migracao/pubcon-pacote.json';

if (! file_exists($arquivo)) {
    WP_CLI::error("não achei o pacote em {$arquivo}");
}

$pacote = json_decode((string) file_get_contents($arquivo), true);

if (! is_array($pacote) || empty($pacote['produtos'])) {
    WP_CLI::error('pacote inválido');
}

// ---- nada de efeito colateral: e-mail, webhook, estoque
add_filter('pre_wp_mail', '__return_false', 9999);
add_filter('woocommerce_webhook_should_deliver', '__return_false', 9999);
add_filter('woocommerce_can_reduce_order_stock', '__return_false', 9999);
add_filter('woocommerce_can_restore_order_stock', '__return_false', 9999);
add_filter('send_password_change_email', '__return_false', 9999);
add_filter('send_email_change_email', '__return_false', 9999);

$chave = static function (string $texto): string {
    $texto = preg_replace('/^\s*e-?books?\s*[:\-–]?\s*/iu', '', $texto);
    $texto = remove_accents(html_entity_decode($texto, ENT_QUOTES, 'UTF-8'));
    $texto = preg_replace('/[^a-z0-9]+/i', ' ', $texto);

    return trim(strtolower($texto));
};

$e_ebook = static fn (string $nome): bool => (bool) preg_match('/^\s*e-?book/iu', $nome);

// =====================================================================
// 1. mapa de produtos: Pubcon → site novo
// =====================================================================
$locais = $wpdb->get_results("SELECT ID, post_title, post_name, post_status FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status IN ('publish','draft','private')");

$por_origem = [];
$por_slug = [];
$por_nome = ['ebook' => [], 'livro' => []];

foreach ($locais as $l) {
    $origem = (int) get_post_meta($l->ID, '_pubcon_id', true);

    if ($origem) {
        $por_origem[$origem] = (int) $l->ID;
    }

    $por_slug[$l->post_name] = (int) $l->ID;
    $tipo = $e_ebook($l->post_title) ? 'ebook' : 'livro';
    $por_nome[$tipo][$chave($l->post_title)] ??= (int) $l->ID;
}

$variacao_de = static function (int $pai, string $formato): int {
    $filhas = get_posts([
        'post_type' => 'product_variation',
        'post_parent' => $pai,
        'post_status' => ['publish', 'private'],
        'numberposts' => -1,
        'fields' => 'ids',
    ]);

    foreach ($filhas as $filha) {
        if (get_post_meta($filha, 'attribute_pa_formato', true) === $formato) {
            return (int) $filha;
        }
    }

    return 0;
};

$mapa = [];       // id Pubcon => ['produto' => base, 'variacao' => id, 'formato' => slug]
$sem_par = [];

foreach ($pacote['produtos'] as $p) {
    $tipo = $e_ebook($p['nome']) ? 'ebook' : 'livro';
    $local = $por_origem[$p['id']] ?? $por_slug[$p['slug']] ?? $por_nome[$tipo][$chave($p['nome'])] ?? 0;

    // produto virtual cujo nome não começa com "E-book" (os mais antigos)
    if (! $local && $p['virtual']) {
        $local = $por_nome['livro'][$chave($p['nome'])] ?? $por_nome['ebook'][$chave($p['nome'])] ?? 0;
    }

    if (! $local) {
        $sem_par[] = $p;
        continue;
    }

    $unificado = (int) get_post_meta($local, '_conexao_unificado_em', true);

    if ($unificado) {
        $mapa[$p['id']] = ['produto' => $unificado, 'variacao' => $variacao_de($unificado, 'e-book'), 'formato' => 'e-book', 'registro' => $local];
    } elseif (get_the_terms($local, 'product_type') && has_term('variable', 'product_type', $local)) {
        $formato = $p['virtual'] ? 'e-book' : 'impresso';
        $mapa[$p['id']] = ['produto' => $local, 'variacao' => $variacao_de($local, $formato), 'formato' => $formato, 'registro' => $local];
    } else {
        $mapa[$p['id']] = ['produto' => $local, 'variacao' => 0, 'formato' => '', 'registro' => $local];
    }
}

WP_CLI::log(sprintf('%s', $aplicar ? '== APLICANDO' : '== SIMULAÇÃO (nada será gravado)'));
WP_CLI::log(sprintf('produtos: %d da Pubcon, %d casados, %d sem par', count($pacote['produtos']), count($mapa), count($sem_par)));

foreach ($sem_par as $p) {
    WP_CLI::log(sprintf('   sem par: #%d %s (%s%s)', $p['id'], $p['nome'], $p['estado'], $p['virtual'] ? ', virtual' : ''));
}

// =====================================================================
// 2. livros: pasta do flipbook e coleções
// =====================================================================
$flipbooks = 0;

foreach ($pacote['produtos'] as $p) {
    if (! isset($mapa[$p['id']])) {
        continue;
    }

    if ($aplicar) {
        update_post_meta($mapa[$p['id']]['registro'], '_pubcon_id', $p['id']);
    }

    if ($p['flipbook'] === '') {
        continue;
    }

    $flipbooks++;

    if ($aplicar) {
        update_post_meta($mapa[$p['id']]['produto'], CONEXAO_LIVRO_FLIPBOOK, $p['flipbook']);
    }
}

$combos = 0;

foreach ((array) $pacote['combos'] as $origem => $incluidos) {
    if (! isset($mapa[(int) $origem])) {
        continue;
    }

    $destinos = [];

    foreach ($incluidos as $id) {
        if (isset($mapa[(int) $id])) {
            $destinos[] = $mapa[(int) $id]['produto'];
        }
    }

    $destinos = array_values(array_unique($destinos));
    $combos++;
    WP_CLI::log(sprintf('coleção #%d → produto %d libera %d de %d livros', $origem, $mapa[(int) $origem]['produto'], count($destinos), count($incluidos)));

    if ($aplicar && $destinos) {
        update_post_meta($mapa[(int) $origem]['produto'], CONEXAO_LIVRO_COMBO, $destinos);
    }
}

WP_CLI::log(sprintf('livros ligados a flipbook: %d | coleções: %d', $flipbooks, $combos));

// =====================================================================
// 3. clientes
// =====================================================================
$usuarios = [];   // id Pubcon => id local
$criados = 0;
$existentes = 0;

foreach ($pacote['usuarios'] as $u) {
    $ja = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_pubcon_usuario' AND meta_value = %s LIMIT 1",
        (string) $u['id']
    ));

    if (! $ja) {
        $por_email = get_user_by('email', $u['email']);
        $ja = $por_email ? (int) $por_email->ID : 0;
    }

    if ($ja) {
        $usuarios[$u['id']] = $ja;
        $existentes++;

        if ($aplicar) {
            update_user_meta($ja, '_pubcon_usuario', (string) $u['id']);
        }

        continue;
    }

    $criados++;

    if (! $aplicar) {
        $usuarios[$u['id']] = -1;
        continue;
    }

    $login = sanitize_user($u['login'], true) ?: 'cliente'.$u['id'];

    if (username_exists($login)) {
        $login .= '-'.$u['id'];
    }

    $novo = wp_insert_user([
        'user_login' => $login,
        'user_email' => $u['email'],
        'user_pass' => wp_generate_password(32, true),
        'display_name' => $u['nome'],
        'first_name' => $u['meta']['first_name'] ?? '',
        'last_name' => $u['meta']['last_name'] ?? '',
        'user_registered' => $u['registro'],
        'role' => 'customer',
    ]);

    if (is_wp_error($novo)) {
        WP_CLI::warning(sprintf('usuário #%d: %s', $u['id'], $novo->get_error_message()));
        $criados--;
        continue;
    }

    // a senha antiga continua valendo: o hash vem como estava
    if (! empty($u['senha'])) {
        $wpdb->update($wpdb->users, ['user_pass' => $u['senha']], ['ID' => $novo]);
        clean_user_cache($novo);
    }

    foreach ($u['meta'] as $campo => $valor) {
        update_user_meta($novo, $campo, $valor);
    }

    update_user_meta($novo, '_pubcon_usuario', (string) $u['id']);
    update_user_meta($novo, '_pubcon_papeis', implode(',', (array) $u['papeis']));

    $usuarios[$u['id']] = (int) $novo;
}

WP_CLI::log(sprintf('clientes: %d no pacote, %d novos, %d já existiam', count($pacote['usuarios']), $criados, $existentes));

// =====================================================================
// 4. pedidos
// =====================================================================
$estados_validos = array_map(static fn ($e) => substr($e, 3), array_keys(wc_get_order_statuses()));
$novos = 0;
$repetidos = 0;
$atualizados = 0;
$itens_sem_produto = 0;
$por_estado = [];

foreach ($pacote['pedidos'] as $p) {
    $ja = wc_get_orders(['limit' => 1, 'return' => 'ids', 'meta_key' => '_pubcon_pedido', 'meta_value' => (string) $p['id'], 'status' => 'any']);

    if ($ja) {
        $repetidos++;

        // o pedido já veio, mas pode ter andado na loja antiga (pago, concluído…)
        $existente = wc_get_order((int) $ja[0]);
        $estado_origem = in_array($p['estado'], $estados_validos, true) ? $p['estado'] : 'on-hold';

        if ($existente && $existente->get_status() !== $estado_origem) {
            $atualizados++;

            if ($aplicar) {
                if ($p['pago']) {
                    $existente->set_date_paid($p['pago']);
                }

                if ($p['concluido']) {
                    $existente->set_date_completed($p['concluido']);
                }

                $existente->set_status($estado_origem, 'Estado atualizado a partir da loja Pubcon.', true);
                $existente->save();
            }
        }

        continue;
    }

    $novos++;
    $por_estado[$p['estado']] = ($por_estado[$p['estado']] ?? 0) + 1;

    foreach ($p['itens'] as $item) {
        if (! isset($mapa[$item['produto']])) {
            $itens_sem_produto++;
        }
    }

    if (! $aplicar) {
        continue;
    }

    $pedido = new WC_Order();
    $pedido->set_created_via('migracao-pubcon');
    $pedido->set_currency($p['moeda'] ?: 'BRL');
    $pedido->set_customer_id(max(0, (int) ($usuarios[$p['cliente']] ?? 0)));
    $pedido->set_prices_include_tax(false);

    $cobranca = $p['cobranca'] + ['email' => $p['email']];
    $pedido->set_address($cobranca, 'billing');
    $pedido->set_address($p['entrega'], 'shipping');

    foreach ($p['itens'] as $item) {
        $destino = $mapa[$item['produto']] ?? ['produto' => 0, 'variacao' => 0, 'formato' => ''];

        $linha = new WC_Order_Item_Product();
        $linha->set_props([
            'name' => $item['nome'],
            'quantity' => $item['quantidade'],
            'subtotal' => $item['subtotal'],
            'total' => $item['total'],
            'product_id' => $destino['produto'],
            'variation_id' => $destino['variacao'],
        ]);

        if ($destino['formato'] && $destino['variacao']) {
            $linha->add_meta_data('pa_formato', $destino['formato'], true);
        }

        $linha->add_meta_data('_pubcon_produto', (string) $item['produto'], true);
        $pedido->add_item($linha);
    }

    foreach ($p['fretes'] as $frete) {
        $linha = new WC_Order_Item_Shipping();
        $linha->set_props(['method_title' => $frete['titulo'] ?: 'Frete', 'method_id' => $frete['metodo'] ?: 'flat_rate', 'total' => $frete['total']]);
        $pedido->add_item($linha);
    }

    foreach ($p['cupons'] as $cupom) {
        $linha = new WC_Order_Item_Coupon();
        $linha->set_props(['code' => $cupom['codigo'], 'discount' => $cupom['desconto']]);
        $pedido->add_item($linha);
    }

    foreach ($p['taxas'] as $taxa) {
        $linha = new WC_Order_Item_Fee();
        $linha->set_props(['name' => $taxa['nome'], 'total' => $taxa['total']]);
        $pedido->add_item($linha);
    }

    $pedido->set_payment_method($p['pagamento']);
    $pedido->set_payment_method_title($p['pagamento_titulo']);

    if ($p['transacao']) {
        $pedido->set_transaction_id($p['transacao']);
    }

    if ($p['nota']) {
        $pedido->set_customer_note($p['nota']);
    }

    $pedido->set_shipping_total($p['frete']);
    $pedido->set_discount_total($p['desconto']);
    $pedido->set_total($p['total']);
    $pedido->set_order_stock_reduced(true);

    $estado = in_array($p['estado'], $estados_validos, true) ? $p['estado'] : 'on-hold';
    $pago = in_array($estado, ['processing', 'completed'], true);

    if ($p['criado']) {
        $pedido->set_date_created($p['criado']);
    }

    if ($p['pago'] || $pago) {
        $pedido->set_date_paid($p['pago'] ?: $p['criado']);
    }

    if ($p['concluido'] || $estado === 'completed') {
        $pedido->set_date_completed($p['concluido'] ?: ($p['pago'] ?: $p['criado']));
    }

    foreach ((array) $p['documentos'] as $meta => $valor) {
        $pedido->update_meta_data($meta, $valor);
    }

    $pedido->update_meta_data('_pubcon_pedido', (string) $p['id']);
    $pedido->update_meta_data('_pubcon_numero', (string) $p['numero']);
    $pedido->set_status($estado, 'Pedido trazido da loja Pubcon.', true);
    $pedido->save();
}

WP_CLI::log(sprintf(
    'pedidos: %d no pacote, %d novos, %d já importados (%d com estado atualizado) | itens sem produto no site novo: %d',
    count($pacote['pedidos']),
    $novos,
    $repetidos,
    $atualizados,
    $itens_sem_produto
));

foreach ($por_estado as $estado => $n) {
    WP_CLI::log(sprintf('   %s: %d', $estado, $n));
}

// =====================================================================
// 5. avaliações
// =====================================================================
$avaliacoes = 0;
$avaliacoes_fora = 0;
$tocados = [];

foreach ($pacote['avaliacoes'] as $a) {
    if (! isset($mapa[$a['produto']])) {
        $avaliacoes_fora++;
        continue;
    }

    $ja = $wpdb->get_var($wpdb->prepare(
        "SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key = '_pubcon_avaliacao' AND meta_value = %s LIMIT 1",
        (string) $a['id']
    ));

    if ($ja) {
        continue;
    }

    $avaliacoes++;

    if (! $aplicar) {
        continue;
    }

    $produto = $mapa[$a['produto']]['produto'];

    $id = wp_insert_comment([
        'comment_post_ID' => $produto,
        'comment_author' => $a['autor'],
        'comment_author_email' => $a['email'],
        'comment_content' => $a['texto'],
        'comment_type' => 'review',
        'comment_approved' => $a['aprovado'],
        'comment_date' => $a['data'],
        'comment_date_gmt' => $a['data_gmt'],
        'user_id' => max(0, (int) ($usuarios[$a['usuario']] ?? 0)),
    ]);

    if ($id) {
        if ($a['nota']) {
            update_comment_meta($id, 'rating', $a['nota']);
        }

        update_comment_meta($id, 'verified', $a['verificado']);
        update_comment_meta($id, '_pubcon_avaliacao', (string) $a['id']);
        $tocados[$produto] = true;
    }
}

foreach (array_keys($tocados) as $produto) {
    WC_Comments::clear_transients($produto);
    $objeto = wc_get_product($produto);

    if ($objeto) {
        WC_Comments::get_average_rating_for_product($objeto);
        WC_Comments::get_rating_counts_for_product($objeto);
        WC_Comments::get_review_count_for_product($objeto);
    }
}

WP_CLI::log(sprintf('avaliações: %d no pacote, %d novas, %d de produto sem par', count($pacote['avaliacoes']), $avaliacoes, $avaliacoes_fora));

// =====================================================================
// 6. cupons
// =====================================================================
$cupons = 0;

foreach ($pacote['cupons'] as $c) {
    if (wc_get_coupon_id_by_code($c['codigo'])) {
        continue;
    }

    $cupons++;

    if (! $aplicar) {
        continue;
    }

    $traduz = static function (array $ids) use ($mapa): array {
        $saida = [];

        foreach ($ids as $id) {
            if (isset($mapa[$id])) {
                $saida[] = $mapa[$id]['variacao'] ?: $mapa[$id]['produto'];
            }
        }

        return array_values(array_unique($saida));
    };

    $cupom = new WC_Coupon();
    $cupom->set_code($c['codigo']);
    $cupom->set_description($c['descricao']);
    $cupom->set_discount_type($c['tipo']);
    $cupom->set_amount($c['valor']);
    $cupom->set_individual_use($c['individual']);
    $cupom->set_free_shipping($c['frete_gratis']);
    $cupom->set_exclude_sale_items($c['exclui_promocao']);
    $cupom->set_product_ids($traduz($c['produtos']));
    $cupom->set_excluded_product_ids($traduz($c['produtos_fora']));

    if ($c['expira']) {
        $cupom->set_date_expires($c['expira']);
    }

    if ($c['uso_maximo']) {
        $cupom->set_usage_limit($c['uso_maximo']);
    }

    if ($c['uso_por_cliente']) {
        $cupom->set_usage_limit_per_user($c['uso_por_cliente']);
    }

    if ($c['minimo']) {
        $cupom->set_minimum_amount($c['minimo']);
    }

    if ($c['maximo']) {
        $cupom->set_maximum_amount($c['maximo']);
    }

    if ($c['emails']) {
        $cupom->set_email_restrictions($c['emails']);
    }

    $cupom->set_usage_count($c['usado']);
    $cupom->save();
}

WP_CLI::log(sprintf('cupons: %d no pacote, %d novos', count($pacote['cupons']), $cupons));

if ($aplicar) {
    wc_delete_product_transients();
    WP_CLI::success('Migração aplicada.');
} else {
    WP_CLI::success('Simulação concluída. Rode com CONEXAO_APLICAR=1 para gravar.');
}
