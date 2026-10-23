<?php
/**
 * Pedidos de todos os canais. A fonte é o Hub Editora (chamada assinada
 * servidor→servidor), só com o e-mail confirmado (inc/email.php); sem
 * confirmação, ou se o Hub falhar, não estiver configurado ou demorar, o
 * app vê os pedidos desta loja (WooCommerce) no mesmo formato, com
 * `fonte: "loja"`. Resultado em cache de 2 minutos por usuário.
 */

if (! defined('ABSPATH')) {
    exit;
}

const CONEXAO_APP_HUB_ROTA = '/api/app/pedidos';
const CONEXAO_APP_CACHE_PEDIDOS = 120;

const CONEXAO_APP_SITUACOES = [
    'aguardando_pagamento' => 'Aguardando pagamento',
    'pago' => 'Pagamento confirmado',
    'em_separacao' => 'Em separação',
    'a_caminho' => 'A caminho',
    'entregue' => 'Entregue',
    'cancelado' => 'Cancelado',
    'problema' => 'Precisa de atenção',
    'concluido_digital' => 'Disponível na estante',
];

// ---------------------------------------------------------------- Hub

/** Assinatura do contrato: hex(hmac_sha256("{ts}\nGET\n/api/app/pedidos\nemail={email}")), e-mail em minúsculas, sem codificar. */
function conexao_app_hub_assinatura(string $timestamp, string $email, string $segredo): string
{
    return hash_hmac('sha256', $timestamp."\nGET\n".CONEXAO_APP_HUB_ROTA."\nemail=".$email, $segredo);
}

/** Pedidos do Hub, ou null se não deu (sem segredo, erro, resposta fora do formato). */
function conexao_app_pedidos_do_hub(string $email): ?array
{
    $segredo = defined('CONEXAO_APP_HUB_SECRET') ? (string) CONEXAO_APP_HUB_SECRET : '';

    if (strlen($segredo) < 32 || $email === '') {
        return null;
    }

    $email = strtolower(trim($email));
    $timestamp = (string) time();
    $url = rtrim((string) CONEXAO_APP_HUB_URL, '/').CONEXAO_APP_HUB_ROTA.'?email='.rawurlencode($email);

    $resposta = wp_remote_get($url, [
        'timeout' => 8,
        'redirection' => 0,
        'headers' => [
            'Accept' => 'application/json',
            'X-Conexao-Timestamp' => $timestamp,
            'X-Conexao-Signature' => conexao_app_hub_assinatura($timestamp, $email, $segredo),
        ],
    ]);

    if (is_wp_error($resposta)) {
        // sem e-mail nem URL completa no log: só o motivo
        error_log('conexao-app: Hub indisponível ('.$resposta->get_error_code().')');

        return null;
    }

    $codigo = (int) wp_remote_retrieve_response_code($resposta);
    $corpo = json_decode((string) wp_remote_retrieve_body($resposta), true);

    if ($codigo !== 200 || ! is_array($corpo) || ! isset($corpo['pedidos']) || ! is_array($corpo['pedidos'])) {
        error_log('conexao-app: Hub respondeu HTTP '.$codigo);

        return null;
    }

    $pedidos = [];

    foreach ($corpo['pedidos'] as $pedido) {
        if (is_array($pedido) && ($limpo = conexao_app_pedido_limpo($pedido))) {
            $pedidos[] = $limpo;
        }
    }

    return $pedidos;
}

/**
 * Só as chaves do contrato saem para o app, mesmo que o Hub mande mais
 * (nada de custo de frete, observação interna etc. por descuido).
 */
function conexao_app_pedido_limpo(array $p): ?array
{
    $ref = (string) ($p['ref'] ?? '');

    if (! preg_match('/^[A-Za-z0-9_-]{1,60}$/', $ref)) {
        return null;
    }

    $situacao = isset(CONEXAO_APP_SITUACOES[$p['situacao'] ?? '']) ? (string) $p['situacao'] : 'problema';
    $texto = static fn ($v): ?string => is_scalar($v) && (string) $v !== '' ? (string) $v : null;

    $itens = [];
    foreach ((array) ($p['itens'] ?? []) as $item) {
        if (is_array($item)) {
            $itens[] = [
                'nome' => (string) ($item['nome'] ?? ''),
                'quantidade' => max(1, (int) ($item['quantidade'] ?? 1)),
                'formato' => $texto($item['formato'] ?? null),
                'livro_id' => ! empty($item['livro_id']) ? (int) $item['livro_id'] : null,
            ];
        }
    }

    $envio = null;
    if (is_array($p['envio'] ?? null)) {
        $e = $p['envio'];
        $envio = [];
        foreach (['transportadora', 'servico', 'codigo', 'url_rastreio', 'postado_em', 'entregue_em', 'previsao'] as $chave) {
            $envio[$chave] = $texto($e[$chave] ?? null);
        }
        if ($envio['url_rastreio'] && ! wp_http_validate_url($envio['url_rastreio'])) {
            $envio['url_rastreio'] = null;
        }
    }

    $eventos = [];
    foreach ((array) ($p['eventos'] ?? []) as $evento) {
        if (is_array($evento)) {
            $eventos[] = [
                'situacao' => (string) ($evento['situacao'] ?? ''),
                'rotulo' => (string) ($evento['rotulo'] ?? ''),
                'em' => $texto($evento['em'] ?? null),
            ];
        }
    }

    return [
        'ref' => $ref,
        'numero' => (string) ($p['numero'] ?? ''),
        'canal' => (string) ($p['canal'] ?? ''),
        'feito_em' => $texto($p['feito_em'] ?? null),
        'total_centavos' => (int) ($p['total_centavos'] ?? 0),
        'situacao' => $situacao,
        'situacao_rotulo' => (string) ($p['situacao_rotulo'] ?? '') ?: CONEXAO_APP_SITUACOES[$situacao],
        'pagamento' => (string) ($p['pagamento'] ?? ''),
        'digital' => (bool) ($p['digital'] ?? false),
        'itens' => $itens,
        'envio' => $envio,
        'eventos' => $eventos,
    ];
}

// ---------------------------------------------------------------- loja (reserva)

/** Código de rastreio gravado no pedido (Melhor Envio; plugin dos Correios). */
function conexao_app_rastreio(WC_Order $pedido): string
{
    if (function_exists('conexao_rastreio_pedido')) {
        $codigo = conexao_rastreio_pedido($pedido);
    } else {
        $codigo = (string) $pedido->get_meta('melhorenvio_tracking');
    }

    if ($codigo === '') {
        $correios = $pedido->get_meta('_correios_tracking_code');
        $codigo = is_array($correios) ? (string) reset($correios) : (string) $correios;
    }

    return trim($codigo);
}

function conexao_app_pedido_e_digital(WC_Order $pedido): bool
{
    if (function_exists('conexao_pedido_digital')) {
        return conexao_pedido_digital($pedido);
    }

    foreach ($pedido->get_items() as $item) {
        $produto = $item instanceof WC_Order_Item_Product ? $item->get_product() : null;

        if (! $produto || ! $produto->is_virtual()) {
            return false;
        }
    }

    return true;
}

/** Formato do item: o atributo da variação, ou virtual/físico no produto simples. */
function conexao_app_formato_item(WC_Order_Item_Product $item): string
{
    $produto = $item->get_product();
    $formato = $produto && $produto->is_type('variation') ? (string) ($produto->get_attributes()['pa_formato'] ?? '') : '';
    $rotulos = ['impresso' => 'Impresso', 'e-book' => 'E-book', 'impresso-e-book' => 'Impresso + e-book'];

    if (isset($rotulos[$formato])) {
        return $rotulos[$formato];
    }

    return $produto && $produto->is_virtual() ? 'E-book' : 'Impresso';
}

/** Transportadora e serviço a partir do nome do frete ("Correios Pac (Melhor Envio) (8 a 9 dias úteis)"). */
function conexao_app_envio_da_loja(WC_Order $pedido, bool $digital): ?array
{
    if ($digital) {
        return null;
    }

    $fretes = $pedido->get_items('shipping');
    $frete = $fretes ? reset($fretes) : null;
    $codigo = conexao_app_rastreio($pedido);

    if (! $frete && $codigo === '') {
        return null;
    }

    $nome = $frete ? trim((string) preg_replace('/\s*\([^)]*\)/u', '', $frete->get_name())) : '';
    $minusculo = mb_strtolower($nome.' '.($frete ? $frete->get_method_id() : ''));
    $transportadora = null;
    $servico = $nome !== '' ? $nome : null;

    if (preg_match('/correios|\bpac\b|sedex/u', $minusculo)) {
        $transportadora = 'Correios';
        $servico = str_contains($minusculo, 'sedex') ? 'SEDEX' : (str_contains($minusculo, 'pac') ? 'PAC' : $servico);
    } elseif (str_contains($minusculo, 'jadlog')) {
        $transportadora = 'Jadlog';
    } elseif (str_contains($minusculo, 'loggi')) {
        $transportadora = 'Loggi';
    }

    $entregue = $pedido->get_status() === 'completed' ? conexao_app_iso_wc($pedido->get_date_completed()) : null;

    return [
        'transportadora' => $transportadora,
        'servico' => $servico,
        'codigo' => $codigo !== '' ? $codigo : null,
        'url_rastreio' => $codigo !== '' ? 'https://www.melhorrastreio.com.br/rastreio/'.rawurlencode($codigo) : null,
        'postado_em' => null,
        'entregue_em' => $entregue,
        'previsao' => null,
    ];
}

/** Pedido do WooCommerce no formato `Pedido` do contrato. */
function conexao_app_pedido_da_loja(WC_Order $pedido): array
{
    $status = $pedido->get_status();
    $digital = conexao_app_pedido_e_digital($pedido);
    $rastreio = $digital ? '' : conexao_app_rastreio($pedido);
    $criado = conexao_app_iso_wc($pedido->get_date_created());
    $pago_em = conexao_app_iso_wc($pedido->get_date_paid());
    $alterado = conexao_app_iso_wc($pedido->get_date_modified());

    // pago e e-book: já está na estante (a biblioteca libera em "processando")
    $situacao = match ($status) {
        'pending', 'on-hold' => 'aguardando_pagamento',
        'processing' => $digital ? 'concluido_digital' : ($rastreio !== '' ? 'a_caminho' : 'em_separacao'),
        'completed' => $digital ? 'concluido_digital' : 'entregue',
        'cancelled', 'refunded', 'failed' => 'cancelado',
        default => $pedido->is_paid() ? 'em_separacao' : 'aguardando_pagamento',
    };

    $pagamento = match ($status) {
        'refunded' => 'reembolsado',
        'cancelled', 'failed' => 'cancelado',
        default => $pedido->is_paid() || $pago_em ? 'pago' : 'pendente',
    };

    $itens = [];
    foreach ($pedido->get_items() as $item) {
        if (! $item instanceof WC_Order_Item_Product) {
            continue;
        }

        $produto_id = (int) $item->get_product_id();
        $base = $produto_id ? wc_get_product($produto_id) : null;
        $le = $produto_id && function_exists('conexao_biblioteca_item_digital')
            && conexao_biblioteca_item_digital($produto_id, (int) $item->get_variation_id());

        $itens[] = [
            'nome' => $base ? html_entity_decode($base->get_name(), ENT_QUOTES, 'UTF-8') : $item->get_name(),
            'quantidade' => (int) $item->get_quantity(),
            'formato' => conexao_app_formato_item($item),
            'livro_id' => $le ? conexao_biblioteca_produto_base($produto_id) : null,
        ];
    }

    $eventos = [['situacao' => 'aguardando_pagamento', 'rotulo' => 'Pedido recebido', 'em' => $criado]];

    if ($pago_em) {
        $eventos[] = ['situacao' => 'pago', 'rotulo' => 'Pagamento confirmado', 'em' => $pago_em];
    }

    if ($digital && in_array($status, ['processing', 'completed'], true)) {
        $eventos[] = ['situacao' => 'concluido_digital', 'rotulo' => 'Disponível na estante', 'em' => $pago_em ?: $alterado];
    } elseif (! $digital && $rastreio !== '' && in_array($status, ['processing', 'completed'], true)) {
        $eventos[] = ['situacao' => 'a_caminho', 'rotulo' => 'Pedido enviado', 'em' => null];
    }

    if (! $digital && $status === 'completed') {
        $eventos[] = ['situacao' => 'entregue', 'rotulo' => 'Pedido concluído', 'em' => conexao_app_iso_wc($pedido->get_date_completed())];
    }

    if (in_array($status, ['cancelled', 'refunded', 'failed'], true)) {
        $rotulo = ['cancelled' => 'Pedido cancelado', 'refunded' => 'Pedido reembolsado', 'failed' => 'Pagamento não aprovado'][$status];
        $eventos[] = ['situacao' => 'cancelado', 'rotulo' => $rotulo, 'em' => $alterado];
    }

    return [
        'ref' => 'loja-'.$pedido->get_id(),
        'numero' => '#'.$pedido->get_order_number(),
        'canal' => (string) apply_filters('conexao_app_canal_loja', 'Loja Conexão Editora'),
        'feito_em' => $criado,
        'total_centavos' => (int) round((float) $pedido->get_total() * 100),
        'situacao' => $situacao,
        'situacao_rotulo' => CONEXAO_APP_SITUACOES[$situacao],
        'pagamento' => $pagamento,
        'digital' => $digital,
        'itens' => $itens,
        'envio' => conexao_app_envio_da_loja($pedido, $digital),
        'eventos' => $eventos,
    ];
}

/** Pedidos do próprio cliente na loja (pela conta, não pelo e-mail), últimos 24 meses. */
function conexao_app_pedidos_da_loja(int $usuario_id): array
{
    if (! function_exists('wc_get_orders')) {
        return [];
    }

    $estados = array_diff(array_keys(wc_get_order_statuses()), ['wc-checkout-draft']);

    $pedidos = wc_get_orders([
        'customer_id' => $usuario_id,
        'type' => 'shop_order',
        'status' => array_map(static fn ($s) => substr($s, 3), $estados),
        'date_created' => '>='.strtotime('-24 months'),
        'orderby' => 'date',
        'order' => 'DESC',
        'limit' => 200,
    ]);

    return array_map('conexao_app_pedido_da_loja', $pedidos);
}

// ---------------------------------------------------------------- junção

function conexao_app_limpa_cache_pedidos(int $user_id): void
{
    delete_transient('conexao_app_pedidos_'.$user_id.'_v');
    delete_transient('conexao_app_pedidos_'.$user_id.'_n');
}

/**
 * {pedidos, fonte, email_verificado} do usuário, com cache de 2 minutos.
 * O Hub (que acha pedidos pelo e-mail) só é consultado com o e-mail
 * confirmado; sem isso, só os pedidos da própria conta na loja.
 */
function conexao_app_pedidos(WP_User $usuario): array
{
    $verificado = conexao_app_email_verificado($usuario);
    // a chave muda com a confirmação: trocar o e-mail não reaproveita o cache do Hub
    $chave = 'conexao_app_pedidos_'.$usuario->ID.($verificado ? '_v' : '_n');
    $cache = get_transient($chave);

    if (is_array($cache) && isset($cache['pedidos'], $cache['fonte'])) {
        return $cache + ['email_verificado' => $verificado];
    }

    $do_hub = $verificado ? conexao_app_pedidos_do_hub((string) $usuario->user_email) : null;
    $resultado = $do_hub !== null
        ? ['pedidos' => $do_hub, 'fonte' => 'hub']
        : ['pedidos' => conexao_app_pedidos_da_loja($usuario->ID), 'fonte' => 'loja'];

    // últimos 24 meses, mais recentes primeiro, venha de onde vier
    $corte = strtotime('-24 months');
    $resultado['pedidos'] = array_values(array_filter(
        $resultado['pedidos'],
        static fn ($p) => ! $p['feito_em'] || strtotime($p['feito_em']) >= $corte
    ));
    usort($resultado['pedidos'], static fn ($a, $b) => (int) strtotime((string) $b['feito_em']) <=> (int) strtotime((string) $a['feito_em']));

    // falha do Hub também fica 2 minutos em cache: não espera o timeout a cada tela
    set_transient($chave, $resultado, CONEXAO_APP_CACHE_PEDIDOS);

    return $resultado + ['email_verificado' => $verificado];
}

// ---------------------------------------------------------------- rotas

/** GET /pedidos */
function conexao_app_rota_pedidos(WP_REST_Request $request)
{
    return conexao_app_pedidos(wp_get_current_user());
}

/** GET /pedidos/{ref} */
function conexao_app_rota_pedido(WP_REST_Request $request)
{
    $ref = (string) $request['ref'];

    foreach (conexao_app_pedidos(wp_get_current_user())['pedidos'] as $pedido) {
        if (hash_equals($pedido['ref'], $ref)) {
            return $pedido;
        }
    }

    return conexao_app_erro('nao_encontrado', 'Pedido não encontrado.');
}
