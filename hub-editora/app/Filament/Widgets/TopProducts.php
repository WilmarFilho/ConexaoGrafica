<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/** Títulos mais vendidos nos últimos 30 dias (pedidos pagos). */
class TopProducts extends Widget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 4;

    protected string $view = 'filament.widgets.top-products';

    public function getRows(): array
    {
        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', 'paid')
            ->where('orders.placed_at', '>=', now()->subDays(30))
            ->select(['order_items.name', DB::raw('sum(order_items.quantity) as qty'), DB::raw('sum(order_items.total_cents) as cents')])
            ->groupBy('order_items.name')
            ->orderByDesc('qty')
            ->limit(6)
            ->get();

        $max = max(1, (int) ($rows->max('qty') ?? 1));

        return $rows->map(fn ($r) => [
            'name' => $r->name,
            'qty' => (int) $r->qty,
            'money' => 'R$ '.number_format($r->cents / 100, 2, ',', '.'),
            'pct' => (int) round($r->qty / $max * 100),
        ])->all();
    }
}
