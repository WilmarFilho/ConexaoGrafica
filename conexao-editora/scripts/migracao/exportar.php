<?php
/**
 * Roda NA LOJA ANTIGA (Pubcon), só lendo: junta clientes, pedidos, avaliações,
 * cupons e o mapa dos produtos num pacote JSON para o site novo importar.
 *
 *   wp eval-file exportar.php                 pacote completo (fica no servidor)
 *   CONEXAO_ANONIMO=1 wp eval-file exportar.php   pacote sem dados pessoais,
 *                                             para testar fora da produção
 *
 * Destino: CONEXAO_PACOTE (padrão: ~/pubcon-pacote.json)
 */

if (! class_exists('WooCommerce')) {
    WP_CLI::error('WooCommerce não está ativo.');
}

global $wpdb;

$anonimo = (bool) getenv('CONEXAO_ANONIMO');
$destino = getenv('CONEXAO_PACOTE') ?: (getenv('HOME').'/pubcon-pacote.json');
$pasta_livros = getenv('CONEXAO_LIVROS') ?: '/home/pubconcom/public_html/livros.pubcon.com.br';

$pacote = [
    'gerado_em' => gmdate('c'),
    'origem' => home_url(),
    'anonimo' => $anonimo,
    'produtos' => [],
    'usuarios' => [],
    'pedidos' => [],
    'avaliacoes' => [],
    'cupons' => [],
    'combos' => [],
];

// ---- produtos: só o necessário para casar com os do site novo
foreach ($wpdb->get_results("SELECT ID, post_title, post_name, post_status FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status IN ('publish','draft','private')") as $p) {
    $pacote['produtos'][] = [
        'id' => (int) $p->ID,
        'nome' => html_entity_decode($p->post_title, ENT_QUOTES, 'UTF-8'),
        'slug' => $p->post_name,
        'estado' => $p->post_status,
        'virtual' => get_post_meta($p->ID, '_virtual', true) === 'yes',
        'flipbook' => is_dir($pasta_livros.'/'.$p->ID) ? (string) $p->ID : '',
    ];
}

// a coleção que libera os 15 volumes está cravada no tema antigo
$pacote['combos'][6845] = [5366, 4045, 5406, 5397, 4016, 3986, 3957, 3930, 3901, 3860, 3830, 3798, 3767, 5341, 3740];

// ---- usuários: clientes e vendedores; administradores ficam de fora
$campos_meta = [
    'first_name', 'last_name',
    'billing_first_name', 'billing_last_name', 'billing_company', 'billing_address_1', 'billing_address_2',
    'billing_city', 'billing_state', 'billing_postcode', 'billing_country', 'billing_email', 'billing_phone',
    'billing_cpf', 'billing_cnpj', 'billing_persontype', 'billing_number', 'billing_neighborhood', 'billing_cellphone',
    'shipping_first_name', 'shipping_last_name', 'shipping_company', 'shipping_address_1', 'shipping_address_2',
    'shipping_city', 'shipping_state', 'shipping_postcode', 'shipping_country', 'shipping_number', 'shipping_neighborhood',
];

$pessoais = ['billing_cpf', 'billing_cnpj', 'billing_phone', 'billing_cellphone', 'billing_address_1', 'billing_address_2',
    'billing_number', 'billing_postcode', 'shipping_address_1', 'shipping_address_2', 'shipping_number', 'shipping_postcode'];

foreach (get_users(['role__in' => ['customer', 'subscriber', 'dc_vendor', 'dc_pending_vendor'], 'number' => -1]) as $u) {
    $meta = [];

    foreach ($campos_meta as $campo) {
        $valor = (string) get_user_meta($u->ID, $campo, true);

        if ($valor !== '') {
            $meta[$campo] = $valor;
        }
    }

    $linha = [
        'id' => (int) $u->ID,
        'login' => $u->user_login,
        'email' => $u->user_email,
        'senha' => $u->user_pass,
        'nome' => $u->display_name,
        'registro' => $u->user_registered,
        'papeis' => array_values((array) $u->roles),
        'meta' => $meta,
    ];

    if ($anonimo) {
        $linha['login'] = 'cliente'.$u->ID;
        $linha['email'] = 'cliente'.$u->ID.'@exemplo.test';
        $linha['senha'] = '';
        $linha['nome'] = 'Cliente '.$u->ID;
        $linha['meta'] = array_diff_key($meta, array_flip($pessoais));

        foreach (['first_name', 'billing_first_name', 'shipping_first_name'] as $c) {
            if (isset($linha['meta'][$c])) {
                $linha['meta'][$c] = 'Cliente';
            }
        }

        foreach (['last_name', 'billing_last_name', 'shipping_last_name'] as $c) {
            if (isset($linha['meta'][$c])) {
                $linha['meta'][$c] = (string) $u->ID;
            }
        }

        if (isset($linha['meta']['billing_email'])) {
            $linha['meta']['billing_email'] = $linha['email'];
        }
    }

    $pacote['usuarios'][] = $linha;
}

// ---- pedidos
$ids = wc_get_orders(['limit' => -1, 'return' => 'ids', 'type' => 'shop_order', 'status' => array_keys(wc_get_order_statuses()), 'orderby' => 'ID', 'order' => 'ASC']);

foreach ($ids as $id) {
    $o = wc_get_order($id);

    if (! $o) {
        continue;
    }

    $endereco = static function (array $dados) use ($anonimo, $o): array {
        $dados = array_filter($dados, static fn ($v) => $v !== '' && $v !== null);

        if ($anonimo) {
            $cliente = (int) $o->get_customer_id();
            $dados = array_intersect_key($dados, array_flip(['city', 'state', 'country']));
            $dados['first_name'] = 'Cliente';
            $dados['last_name'] = (string) ($cliente ?: 'visitante');
        }

        return $dados;
    };

    $itens = [];

    foreach ($o->get_items() as $item) {
        $itens[] = [
            'produto' => (int) $item->get_product_id(),
            'variacao' => (int) $item->get_variation_id(),
            'nome' => $item->get_name(),
            'quantidade' => (float) $item->get_quantity(),
            'subtotal' => (float) $item->get_subtotal(),
            'total' => (float) $item->get_total(),
        ];
    }

    $fretes = [];

    foreach ($o->get_items('shipping') as $frete) {
        $fretes[] = ['titulo' => $frete->get_method_title(), 'metodo' => $frete->get_method_id(), 'total' => (float) $frete->get_total()];
    }

    $cupons = [];

    foreach ($o->get_items('coupon') as $cupom) {
        $cupons[] = ['codigo' => $cupom->get_code(), 'desconto' => (float) $cupom->get_discount()];
    }

    $taxas = [];

    foreach ($o->get_items('fee') as $taxa) {
        $taxas[] = ['nome' => $taxa->get_name(), 'total' => (float) $taxa->get_total()];
    }

    $email = $o->get_billing_email();
    $cliente = (int) $o->get_customer_id();

    $pacote['pedidos'][] = [
        'id' => (int) $o->get_id(),
        'numero' => (string) $o->get_order_number(),
        'estado' => $o->get_status(),
        'cliente' => $cliente,
        'email' => $anonimo ? ($cliente ? "cliente{$cliente}@exemplo.test" : 'visitante'.$o->get_id().'@exemplo.test') : $email,
        'criado' => $o->get_date_created() ? $o->get_date_created()->date('Y-m-d H:i:s') : '',
        'pago' => $o->get_date_paid() ? $o->get_date_paid()->date('Y-m-d H:i:s') : '',
        'concluido' => $o->get_date_completed() ? $o->get_date_completed()->date('Y-m-d H:i:s') : '',
        'moeda' => $o->get_currency(),
        'total' => (float) $o->get_total(),
        'frete' => (float) $o->get_shipping_total(),
        'desconto' => (float) $o->get_discount_total(),
        'pagamento' => $o->get_payment_method(),
        'pagamento_titulo' => $o->get_payment_method_title(),
        'transacao' => $anonimo ? '' : (string) $o->get_transaction_id(),
        'nota' => $anonimo ? '' : (string) $o->get_customer_note(),
        'cobranca' => $endereco($o->get_address('billing')),
        'entrega' => $endereco($o->get_address('shipping')),
        'documentos' => $anonimo ? [] : array_filter([
            '_billing_cpf' => (string) $o->get_meta('_billing_cpf'),
            '_billing_cnpj' => (string) $o->get_meta('_billing_cnpj'),
            '_billing_persontype' => (string) $o->get_meta('_billing_persontype'),
            '_billing_number' => (string) $o->get_meta('_billing_number'),
            '_billing_neighborhood' => (string) $o->get_meta('_billing_neighborhood'),
            '_billing_cellphone' => (string) $o->get_meta('_billing_cellphone'),
            '_shipping_number' => (string) $o->get_meta('_shipping_number'),
            '_shipping_neighborhood' => (string) $o->get_meta('_shipping_neighborhood'),
        ]),
        'itens' => $itens,
        'fretes' => $fretes,
        'cupons' => $cupons,
        'taxas' => $taxas,
    ];
}

// ---- avaliações de produto (aprovadas e pendentes; lixo e spam ficam)
foreach ($wpdb->get_results("SELECT * FROM {$wpdb->comments} WHERE comment_type = 'review' AND comment_approved IN ('0','1') ORDER BY comment_ID") as $c) {
    $pacote['avaliacoes'][] = [
        'id' => (int) $c->comment_ID,
        'produto' => (int) $c->comment_post_ID,
        'autor' => $anonimo ? 'Leitor' : $c->comment_author,
        'email' => $anonimo ? 'leitor'.$c->comment_ID.'@exemplo.test' : $c->comment_author_email,
        'usuario' => (int) $c->user_id,
        'data' => $c->comment_date,
        'data_gmt' => $c->comment_date_gmt,
        'texto' => $c->comment_content,
        'aprovado' => (string) $c->comment_approved,
        'nota' => (int) get_comment_meta($c->comment_ID, 'rating', true),
        'verificado' => (int) get_comment_meta($c->comment_ID, 'verified', true),
    ];
}

// ---- cupons
foreach (get_posts(['post_type' => 'shop_coupon', 'post_status' => 'publish', 'numberposts' => -1]) as $post) {
    $c = new WC_Coupon($post->ID);

    $pacote['cupons'][] = [
        'codigo' => $c->get_code(),
        'descricao' => $c->get_description(),
        'tipo' => $c->get_discount_type(),
        'valor' => (float) $c->get_amount(),
        'expira' => $c->get_date_expires() ? $c->get_date_expires()->date('Y-m-d H:i:s') : '',
        'uso_maximo' => (int) $c->get_usage_limit(),
        'uso_por_cliente' => (int) $c->get_usage_limit_per_user(),
        'usado' => (int) $c->get_usage_count(),
        'individual' => $c->get_individual_use(),
        'frete_gratis' => $c->get_free_shipping(),
        'minimo' => (float) $c->get_minimum_amount(),
        'maximo' => (float) $c->get_maximum_amount(),
        'exclui_promocao' => $c->get_exclude_sale_items(),
        'produtos' => array_map('intval', $c->get_product_ids()),
        'produtos_fora' => array_map('intval', $c->get_excluded_product_ids()),
        'emails' => $anonimo ? [] : $c->get_email_restrictions(),
    ];
}

file_put_contents($destino, wp_json_encode($pacote, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
@chmod($destino, 0600);

WP_CLI::success(sprintf(
    '%s: %d produtos, %d usuários, %d pedidos, %d avaliações, %d cupons → %s (%s)',
    $anonimo ? 'pacote anônimo' : 'pacote completo',
    count($pacote['produtos']),
    count($pacote['usuarios']),
    count($pacote['pedidos']),
    count($pacote['avaliacoes']),
    count($pacote['cupons']),
    $destino,
    size_format((int) filesize($destino))
));
