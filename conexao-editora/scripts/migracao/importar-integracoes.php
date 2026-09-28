<?php
/**
 * Roda NO SITE NOVO: aplica as configurações de pagamento e frete trazidas da
 * Pubcon. Nenhum valor é mostrado na tela.
 *
 *   CONEXAO_PACOTE=~/integracoes.json wp eval-file importar-integracoes.php
 */

$arquivo = getenv('CONEXAO_PACOTE') ?: (getenv('HOME').'/integracoes.json');
$pacote = file_exists($arquivo) ? json_decode((string) file_get_contents($arquivo), true) : null;

if (! is_array($pacote) || empty($pacote['opcoes'])) {
    WP_CLI::error('pacote de integrações não encontrado ou inválido');
}

$de = untrailingslashit((string) $pacote['origem']);
$para = untrailingslashit(home_url());

// endereços da loja antiga dentro das configurações passam a apontar para cá
$troca = static function ($valor) use (&$troca, $de, $para) {
    if (is_array($valor)) {
        return array_map($troca, $valor);
    }

    return is_string($valor) ? str_replace($de, $para, $valor) : $valor;
};

foreach ($pacote['opcoes'] as $nome => $valor) {
    update_option($nome, $troca($valor));
    WP_CLI::log("opção aplicada: {$nome}");
}

foreach ($pacote['zonas'] as $z) {
    $zona = null;

    if ($z['resto']) {
        $zona = new WC_Shipping_Zone(0);
    } else {
        foreach (WC_Shipping_Zones::get_zones() as $existente) {
            if ($existente['zone_name'] === $z['nome']) {
                $zona = new WC_Shipping_Zone($existente['zone_id']);
            }
        }

        if (! $zona) {
            $zona = new WC_Shipping_Zone();
            $zona->set_zone_name($z['nome']);
            $zona->set_locations($z['locais']);
            $zona->save();
        }
    }

    $presentes = array_map(static fn ($m) => $m->id, $zona->get_shipping_methods());

    foreach ($z['metodos'] as $m) {
        if (in_array($m['id'], $presentes, true)) {
            WP_CLI::log("frete já existia: [{$z['nome']}] {$m['id']}");
            continue;
        }

        $instancia = $zona->add_shipping_method($m['id']);

        if (! $instancia) {
            WP_CLI::warning("método de frete indisponível no site novo: {$m['id']}");
            continue;
        }

        update_option("woocommerce_{$m['id']}_{$instancia}_settings", $troca($m['ajustes']));
        WP_CLI::log("frete criado: [{$z['nome']}] {$m['id']}");
    }
}

WC_Cache_Helper::get_transient_version('shipping', true);
WP_CLI::success('Integrações aplicadas.');
