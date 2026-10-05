<?php
/**
 * Acompanhe seu pedido: o cliente informa o número do pedido e o e-mail da
 * compra e vê a situação, as etapas e, quando houver, o código de rastreio.
 */

if (! defined('ABSPATH')) {
    exit;
}

/** Tentativas erradas por IP antes de pausar a busca (contra quem chuta números). */
const CONEXAO_ACOMPANHAR_TENTATIVAS = 10;

/** Código de rastreio do pedido (o plugin do Melhor Envio grava em melhorenvio_tracking). */
function conexao_rastreio_pedido(WC_Order $pedido): string
{
    $codigo = $pedido->get_meta('melhorenvio_tracking');

    return (string) apply_filters('conexao_rastreio_pedido', is_string($codigo) ? trim($codigo) : '', $pedido);
}

/**
 * Pedido com esse número e esse e-mail de compra. O número é o que o cliente vê
 * (nos pedidos trazidos da Pubcon, o número antigo).
 */
function conexao_busca_pedido(string $numero, string $email): ?WC_Order
{
    $numero = ltrim(trim($numero), '#');
    $email = trim($email);

    if ($numero === '' || ! is_email($email)) {
        return null;
    }

    $candidatos = wc_get_orders(['limit' => 3, 'type' => 'shop_order', 'meta_key' => '_pubcon_numero', 'meta_value' => $numero]);

    if (ctype_digit($numero)) {
        $pedido = wc_get_order((int) $numero);

        if ($pedido instanceof WC_Order && $pedido->get_type() === 'shop_order') {
            $candidatos[] = $pedido;
        }
    }

    foreach ($candidatos as $pedido) {
        if ((string) $pedido->get_order_number() === $numero
            && strcasecmp($pedido->get_billing_email(), $email) === 0
            && $pedido->get_status() !== 'checkout-draft') {
            return $pedido;
        }
    }

    return null;
}

/** Só e-books (nada a enviar pelo correio)? */
function conexao_pedido_digital(WC_Order $pedido): bool
{
    foreach ($pedido->get_items() as $item) {
        $produto = $item instanceof WC_Order_Item_Product ? $item->get_product() : null;

        if (! $produto || ! $produto->is_virtual()) {
            return false;
        }
    }

    return true;
}

/**
 * Etapas do pedido, na ordem: [rótulo, detalhe, 'feito' | 'atual' | 'futuro'].
 * Cancelado, reembolsado e com falha não têm etapas (a página mostra um aviso).
 *
 * @return array<int, array{0: string, 1: string, 2: string}>
 */
function conexao_etapas_pedido(WC_Order $pedido): array
{
    $status = $pedido->get_status();

    if (in_array($status, ['cancelled', 'refunded', 'failed'], true)) {
        return [];
    }

    $data = static fn (?WC_DateTime $d): string => $d ? wc_format_datetime($d, 'd/m/Y') : '';
    $digital = conexao_pedido_digital($pedido);
    $rastreio = conexao_rastreio_pedido($pedido);

    $etapas = $digital
        ? [
            ['Pedido recebido', $data($pedido->get_date_created())],
            ['Pagamento aprovado', $data($pedido->get_date_paid())],
            ['E-book liberado', 'Na sua biblioteca, em Minha conta'],
        ]
        : [
            ['Pedido recebido', $data($pedido->get_date_created())],
            ['Pagamento aprovado', $data($pedido->get_date_paid())],
            ['Em preparação', ''],
            ['Enviado', $rastreio !== '' ? 'Código '.$rastreio : ''],
            ['Concluído', $data($pedido->get_date_completed())],
        ];

    // até onde o pedido já chegou: o índice da etapa em andamento
    $atual = match (true) {
        $status === 'completed' => count($etapas),
        // com código de rastreio o pacote já saiu: "Enviado" é a etapa em andamento
        $status === 'processing' && ! $digital => $rastreio !== '' ? 3 : 2,
        $status === 'processing' => 3,
        default => 1, // pendente ou aguardando: falta o pagamento
    };

    foreach ($etapas as $i => &$etapa) {
        $etapa[2] = $i < $atual ? 'feito' : ($i === $atual ? 'atual' : 'futuro');

        if ($etapa[2] === 'futuro') {
            $etapa[1] = '';
        }
    }

    unset($etapa);

    if ($atual === 1 && isset($etapas[1])) {
        $etapas[1][0] = 'Aguardando pagamento';
    }

    return $etapas;
}

/**
 * Trata o envio do formulário. Devolve [pedido|null, mensagem de erro].
 *
 * @return array{0: ?WC_Order, 1: string}
 */
function conexao_acompanhar_envio(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ! isset($_POST['conexao_acompanhar_nonce'])) {
        return [null, ''];
    }

    if (! wp_verify_nonce(sanitize_key(wp_unslash($_POST['conexao_acompanhar_nonce'])), 'conexao_acompanhar')) {
        return [null, 'A página ficou aberta por muito tempo. Tente de novo.'];
    }

    $chave = 'conexao_acompanhar_'.md5((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $erros = (int) get_transient($chave);

    if ($erros >= CONEXAO_ACOMPANHAR_TENTATIVAS) {
        return [null, 'Muitas tentativas seguidas. Aguarde alguns minutos ou fale com a gente.'];
    }

    $pedido = conexao_busca_pedido(
        sanitize_text_field(wp_unslash($_POST['pedido'] ?? '')),
        sanitize_email(wp_unslash($_POST['email'] ?? ''))
    );

    if (! $pedido) {
        set_transient($chave, $erros + 1, 15 * MINUTE_IN_SECONDS);

        return [null, 'Não encontramos um pedido com esse número e esse e-mail. Confira os dados no e-mail de confirmação da compra.'];
    }

    return [$pedido, ''];
}
