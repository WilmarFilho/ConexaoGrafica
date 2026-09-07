<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        /** @var Order $order */
        $order = $this->getRecord();

        return 'Pedido #'.($order->external_number ?: $order->external_id);
    }

    public function getSubheading(): ?string
    {
        /** @var Order $order */
        $order = $this->getRecord();

        return trim(($order->channel?->name ?? '').' · recebido '.($order->placed_at?->format('d/m/Y \à\s H:i') ?? ''));
    }
}
