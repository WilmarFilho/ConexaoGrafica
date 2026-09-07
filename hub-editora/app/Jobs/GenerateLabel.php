<?php

namespace App\Jobs;

use App\Integrations\MelhorEnvio\ShipmentService;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Compra e gera a etiqueta de um pedido em segundo plano (o lote da Central
 * de Expedição despacha um job por pedido, então um erro não trava os outros).
 */
class GenerateLabel implements ShouldQueue
{
    use Queueable;

    public int $tries = 1; // o ShipmentService já registra a falha como pendência

    public function __construct(
        public int $orderId,
        public ?int $serviceId = null,
    ) {}

    public function handle(ShipmentService $service): void
    {
        $order = Order::query()->with(['customer', 'items', 'channel'])->find($this->orderId);

        if (! $order) {
            return;
        }

        $service->buy($order, $this->serviceId);
    }
}
