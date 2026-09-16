<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\SyncLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Política de retenção da Amazon: dados pessoais do comprador somem 30 dias
 * depois do envio. Ficam número do pedido, itens, valores, cidade/UF e rastreio.
 */
class PurgePii extends Command
{
    protected $signature = 'hub:purge-pii {--days= : Dias após o envio (padrão: config, máximo 30)} {--dry-run : Só mostra o que faria}';

    protected $description = 'Apaga dados pessoais de pedidos da Amazon enviados há mais de N dias (exigência da Amazon)';

    public function handle(): int
    {
        $days = $this->option('days') !== null
            ? min(30, max(1, (int) $this->option('days')))
            : (int) config('hub.retention.amazon_pii_days', 30);
        $cutoff = now()->subDays($days);
        $amazon = Channel::bySlug(Channel::AMAZON);

        if (! $amazon) {
            $this->warn('Canal Amazon não existe.');

            return self::SUCCESS;
        }

        $orders = Order::query()
            ->where('channel_id', $amazon->id)
            ->whereNull('pii_purged_at')
            ->where(function ($q) use ($cutoff) {
                $q->whereHas('shipment', fn ($s) => $s->where('shipped_at', '<=', $cutoff)->orWhere('delivered_at', '<=', $cutoff))
                    ->orWhere(fn ($w) => $w->whereIn('status', ['shipped', 'delivered', 'cancelled'])->where('updated_at', '<=', $cutoff));
            })
            ->with(['customer', 'shipment'])
            ->get();

        $this->info("Pedidos elegíveis: {$orders->count()} (enviados há mais de {$days} dias).");

        if ($this->option('dry-run')) {
            foreach ($orders as $o) {
                $this->line("  {$o->external_number} · {$o->customer?->name} · {$o->ship_city_state}");
            }

            return self::SUCCESS;
        }

        $n = 0;
        foreach ($orders as $order) {
            DB::transaction(function () use ($order, &$n) {
                $raw = $order->raw ?? [];
                unset($raw['contato'], $raw['transporte']['etiqueta'], $raw['transporte']['contato']);

                $order->forceFill([
                    'ship_name' => null, 'ship_street' => null, 'ship_number' => null, 'ship_complement' => null,
                    'ship_district' => null, 'ship_zip' => null, 'ship_reference' => null,
                    'raw' => $raw,
                    'pii_purged_at' => now(),
                ])->save();

                if ($order->shipment) {
                    $sraw = $order->shipment->raw ?? [];
                    unset($sraw['cart']['to'], $sraw['tracking']['to']);
                    $order->shipment->forceFill(['raw' => $sraw])->save();
                }

                // Cliente só da Amazon e sem pedido ainda ativo: anonimiza também.
                $customer = $order->customer;
                if ($customer && ! Customer::query()->where('id', $customer->id)->whereHas('orders', fn ($q) => $q->whereNull('pii_purged_at'))->exists()) {
                    $customer->forceFill(['name' => 'Comprador Amazon (dados apagados)', 'email' => null, 'phone' => null, 'document' => null])->save();
                }

                SyncLog::record($order->channel, SyncLog::OUT, 'pii.purged', $order, "Dados pessoais apagados ({$order->external_number})");
                $n++;
            });
        }

        $this->info("{$n} pedido(s) anonimizado(s).");

        return self::SUCCESS;
    }
}
