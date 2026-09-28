<?php
/**
 * Roda NA LOJA ANTIGA (Pubcon), só lendo: junta as configurações de pagamento
 * (Pagar.me) e de frete (Melhor Envio) para o site novo usar as mesmas contas.
 * O arquivo leva credenciais: fica com permissão 600 e é apagado após o uso.
 *
 *   CONEXAO_PACOTE=~/integracoes.json wp eval-file exportar-integracoes.php
 */

$destino = getenv('CONEXAO_PACOTE') ?: (getenv('HOME').'/integracoes.json');

$opcoes = [
    // Pagar.me
    'wcmp_pagarme_settings',
    'woocommerce_woo-pagarme-payments-credit_card_settings',
    'woocommerce_woo-pagarme-payments-pix_settings',
    'woocommerce_woo-pagarme-payments-billet_settings',
    'woocommerce_pagarme_wallet_endpoint',
    // Melhor Envio
    'wpmelhorenvio_token',
    'wpmelhorenvio_token_environment',
    'melhorenvio_user_info',
    'melhorenvio_address_selected_v2',
    'melhorenvio_ar',
    'melhorenvio_mp',
    'melhor_envio_option_label',
    'melhor_envio_option_dimension_default',
    'melhor_envio_option_where_show_calculator',
];

$pacote = ['origem' => home_url(), 'opcoes' => [], 'zonas' => []];

foreach ($opcoes as $nome) {
    $valor = get_option($nome, null);

    if ($valor !== null) {
        $pacote['opcoes'][$nome] = $valor;
    }
}

// zonas de frete: só os métodos ligados e que existem no site novo
$aceitos = ['melhorenvio_correios_pac', 'melhorenvio_correios_sedex', 'free_shipping', 'flat_rate', 'local_pickup'];

foreach (array_merge(WC_Shipping_Zones::get_zones(), [['zone_id' => 0]]) as $z) {
    $zona = new WC_Shipping_Zone($z['zone_id']);
    $metodos = [];

    foreach ($zona->get_shipping_methods(true) as $m) {
        if (in_array($m->id, $aceitos, true)) {
            $metodos[] = ['id' => $m->id, 'ordem' => (int) $m->method_order, 'ajustes' => (array) $m->instance_settings];
        }
    }

    if ($metodos) {
        $pacote['zonas'][] = [
            'nome' => $zona->get_zone_name(),
            'resto' => (int) $z['zone_id'] === 0,
            'locais' => array_map(static fn ($l) => ['code' => $l->code, 'type' => $l->type], $zona->get_zone_locations()),
            'metodos' => $metodos,
        ];
    }
}

file_put_contents($destino, wp_json_encode($pacote));
chmod($destino, 0600);

WP_CLI::success(sprintf('%d opções e %d zona(s) de frete exportadas.', count($pacote['opcoes']), count($pacote['zonas'])));
