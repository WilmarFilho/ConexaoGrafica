<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\ReaderApp\OrderPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pedidos de um leitor (por e-mail) em todos os canais, para o app. Quem chama
 * é o site, já com o leitor autenticado (VerifyAppSignature); e-mail desconhecido
 * devolve lista vazia, nunca 404 (não revela quem é cliente).
 */
class AppOrdersController extends Controller
{
    public const MONTHS = 24;

    public function __invoke(Request $request): JsonResponse
    {
        $email = mb_strtolower(trim((string) $request->query('email', '')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['pedidos' => []]);
        }

        $orders = Order::query()
            ->whereIn('customer_id', Customer::query()->where('email', $email)->select('id'))
            ->where(fn ($q) => $q->where('placed_at', '>=', now()->subMonths(self::MONTHS))->orWhereNull('placed_at'))
            ->with(['channel', 'items.product', 'shipment'])
            ->orderByDesc('placed_at')
            ->limit(100)
            ->get();

        return response()->json(['pedidos' => $orders->map(fn (Order $o) => OrderPresenter::present($o))->values()]);
    }
}
