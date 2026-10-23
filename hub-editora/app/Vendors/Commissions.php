<?php

namespace App\Vendors;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\SyncLog;
use App\Models\Vendor;
use App\Models\VendorCommission;
use Illuminate\Support\Facades\DB;

/**
 * Espelha um pedido nas comissões dos vendedores. Idempotente: pode rodar a
 * cada importação e a cada mudança de etapa. Regras:
 *
 * - Base = valor dos itens do produto no pedido (sem frete, já com desconto);
 *   comissão = base × % do produto (ou do vendedor), arredondada no centavo.
 * - Pedido sem pagamento → pendente; pago → a repassar; cancelado, estornado
 *   ou falho → cancelada.
 * - Pedido anterior a `commissions_start_at` do vendedor não gera comissão
 *   (o que veio antes foi acertado fora do hub, ex.: MVX).
 * - Comissão já repassada (ou presa num repasse em aberto) não muda: se o
 *   pedido cair depois disso, nasce um estorno negativo que desconta no
 *   próximo repasse. Se o pedido voltar a valer, o estorno não repassado some.
 */
class Commissions
{
    /** Pagamento que derruba a venda. */
    private const LOST_PAYMENT = ['refunded', 'failed', 'cancelled'];

    public static function make(): self
    {
        return new self;
    }

    public function syncOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $state = $this->orderState($order);
            $wanted = $this->wanted($order);
            $existing = VendorCommission::query()->where('order_id', $order->id)->lockForUpdate()->get();
            $sales = $existing->where('kind', VendorCommission::SALE)->keyBy('product_id');
            $reversals = $existing->where('kind', VendorCommission::REVERSAL)->keyBy('product_id');

            foreach ($wanted as $productId => $line) {
                $sale = $sales->get($productId);

                if (! $sale) {
                    if ($state !== VendorCommission::CANCELLED) {
                        VendorCommission::create([...$line, 'order_id' => $order->id, 'kind' => VendorCommission::SALE, 'status' => $state]);
                    }

                    continue;
                }

                if ($sale->isLocked()) {
                    $state === VendorCommission::CANCELLED
                        ? $this->reverse($sale, $reversals->get($productId))
                        : $this->dropReversal($reversals->get($productId));

                    continue;
                }

                $sale->update([...$line, 'status' => $state]);
            }

            // Produto que saiu do pedido, perdeu o vendedor ou ficou antes do início.
            // (except() de coleção Eloquent filtra pela chave primária, não pela chave do keyBy)
            foreach ($sales->reject(fn (VendorCommission $sale) => isset($wanted[$sale->product_id])) as $sale) {
                if ($sale->isLocked()) {
                    $this->reverse($sale, $reversals->get($sale->product_id));
                } elseif ($state === VendorCommission::CANCELLED) {
                    $sale->update(['status' => VendorCommission::CANCELLED]);
                } else {
                    $sale->delete();
                }
            }
        });
    }

    /** Recalcula todos os pedidos com produtos do vendedor (ou com comissão dele). */
    public function syncVendor(Vendor $vendor): int
    {
        $orders = Order::query()
            ->whereHas('items.product', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->orWhereIn('id', VendorCommission::query()->where('vendor_id', $vendor->id)->select('order_id'))
            ->get();

        $orders->each(fn (Order $o) => $this->syncOrder($o));

        return $orders->count();
    }

    public function orderState(Order $order): string
    {
        if ($order->status === OrderStatus::Cancelled || in_array($order->payment_status, self::LOST_PAYMENT, true)) {
            return VendorCommission::CANCELLED;
        }

        $paidStages = [OrderStatus::Paid, OrderStatus::Picking, OrderStatus::Checked, OrderStatus::LabelGenerated,
            OrderStatus::ReadyToShip, OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Fulfilled];

        return $order->payment_status === 'paid' || in_array($order->status, $paidStages, true)
            ? VendorCommission::AVAILABLE
            : VendorCommission::PENDING;
    }

    /**
     * Linhas de comissão que o pedido deveria ter, por produto.
     *
     * @return array<int, array<string, mixed>>
     */
    public function wanted(Order $order): array
    {
        $items = $order->items()->with('product.vendor')->get()
            ->filter(fn ($item) => $item->product?->vendor !== null);

        $lines = [];
        foreach ($items->groupBy('product_id') as $productId => $group) {
            /** @var Product $product */
            $product = $group->first()->product;
            $vendor = $product->vendor;
            $start = $vendor->commissions_start_at;

            if ($start && $order->placed_at && $order->placed_at->lt($start->copy()->startOfDay())) {
                continue;
            }

            $base = (int) $group->sum('total_cents');
            $rate = (float) ($product->vendor_commission_pct ?? $vendor->commission_pct);

            $lines[(int) $productId] = [
                'vendor_id' => $vendor->id,
                'product_id' => (int) $productId,
                'product_name' => $product->name,
                'quantity' => (int) $group->sum('quantity'),
                'base_cents' => $base,
                'rate' => $rate,
                'amount_cents' => (int) round($base * $rate / 100),
                'earned_at' => $order->placed_at ?? $order->created_at,
            ];
        }

        return $lines;
    }

    private function reverse(VendorCommission $sale, ?VendorCommission $reversal): void
    {
        if ($reversal) {
            return; // já estornada (repassado ou não, o estorno vale uma vez)
        }

        VendorCommission::create([
            'vendor_id' => $sale->vendor_id,
            'order_id' => $sale->order_id,
            'product_id' => $sale->product_id,
            'kind' => VendorCommission::REVERSAL,
            'product_name' => $sale->product_name,
            'quantity' => $sale->quantity,
            'base_cents' => -$sale->base_cents,
            'rate' => $sale->rate,
            'amount_cents' => -$sale->amount_cents,
            'status' => VendorCommission::AVAILABLE,
            'earned_at' => now(),
        ]);

        SyncLog::record(null, SyncLog::IN, 'vendor.commission_reversed', $sale->order,
            sprintf('Pedido caiu depois do repasse: estorno de %s na comissão de %s (%s)', Vendor::money((int) $sale->amount_cents), $sale->vendor?->name, $sale->product_name),
            ['vendor_id' => $sale->vendor_id, 'commission_id' => $sale->id], 'warning');
    }

    /** Pedido voltou a valer: estorno ainda não repassado deixa de existir. */
    private function dropReversal(?VendorCommission $reversal): void
    {
        if ($reversal && ! $reversal->isLocked()) {
            $reversal->delete();
        }
    }
}
