<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ImportChannelOrder;
use App\Models\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Recebe os webhooks do Pagar.me (order.paid, order.canceled, charge.* ...).
 *
 * O Pagar.me não assina o corpo. A proteção é dupla: o Dash permite
 * configurar autenticação Basic no webhook (senha = PAGARME_WEBHOOK_SECRET;
 * usuário livre) e, de qualquer forma, o hub só usa o id do evento para
 * rebuscar o pedido na API com a própria chave. Também aceita ?token=.
 */
class PagarMeWebhookController extends Controller
{
    /** Eventos que interessam à expedição; o resto responde 200 e some. */
    private const RELEVANT = ['order.', 'charge.paid', 'charge.refunded', 'charge.chargedback', 'charge.payment_failed'];

    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('hub.pagarme.webhook_secret');

        if ($secret === '') {
            return response()->json(['error' => 'webhook não configurado'], 503);
        }

        $given = (string) ($request->getPassword() ?? $request->query('token', ''));
        if ($given === '' || ! hash_equals($secret, $given)) {
            return response()->json(['error' => 'não autorizado'], 401);
        }

        $type = (string) $request->input('type', '');
        $data = (array) $request->input('data', []);

        if (! Str::startsWith($type, self::RELEVANT)) {
            return response()->json(['ok' => true, 'ignored' => $type ?: 'sem tipo']);
        }

        // order.* traz o pedido em data; charge.* traz a cobrança com data.order.
        $orderId = Str::startsWith($type, 'order.')
            ? ($data['id'] ?? null)
            : ($data['order']['id'] ?? null);

        if (! is_string($orderId) || ! Str::startsWith($orderId, 'or_')) {
            return response()->json(['error' => 'evento sem id de pedido'], 422);
        }

        ImportChannelOrder::dispatch(Channel::PAGARME, $orderId, $type);

        return response()->json(['ok' => true, 'queued' => $orderId], 202);
    }
}
