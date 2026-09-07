<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ImportChannelOrder;
use App\Models\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recebe os webhooks de pedido do WooCommerce (order.created / order.updated).
 *
 * Cadastro na loja: WooCommerce → Configurações → Avançado → Webhooks →
 * tópico "Pedido atualizado" (e "Pedido criado"), URL /webhooks/woocommerce,
 * segredo = WOO_WEBHOOK_SECRET. O Woo assina o corpo com HMAC-SHA256 e manda
 * em X-WC-Webhook-Signature; sem assinatura válida nada é processado.
 */
class WooCommerceWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('hub.woocommerce.webhook_secret');

        if ($secret === '') {
            return response()->json(['error' => 'webhook não configurado'], 503);
        }

        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));
        $given = (string) $request->header('X-WC-Webhook-Signature', '');

        if ($given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['error' => 'assinatura inválida'], 401);
        }

        // Ao salvar o webhook, o Woo manda um "ping" form-encoded só com webhook_id.
        $form = [];
        if (str_contains((string) $request->header('Content-Type'), 'form-urlencoded')) {
            parse_str($request->getContent(), $form);
        }
        if (($request->input('webhook_id') ?? $form['webhook_id'] ?? null) && ! $request->filled('id')) {
            return response()->json(['ok' => true, 'ping' => true]);
        }

        $orderId = (int) $request->input('id');
        if ($orderId <= 0) {
            return response()->json(['error' => 'payload sem id de pedido'], 422);
        }

        // Pedidos-filho do multi-vendor nem entram na fila.
        if ((int) $request->input('parent_id', 0) > 0) {
            return response()->json(['ok' => true, 'ignored' => 'pedido-filho']);
        }

        ImportChannelOrder::dispatch(
            Channel::WOOCOMMERCE,
            (string) $orderId,
            (string) $request->header('X-WC-Webhook-Topic', 'order'),
        );

        return response()->json(['ok' => true, 'queued' => $orderId], 202);
    }
}
