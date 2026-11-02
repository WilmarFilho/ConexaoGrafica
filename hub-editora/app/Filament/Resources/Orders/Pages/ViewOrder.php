<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Actions\ChangeOrderStatusAction;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Support\Fuso;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ChangeOrderStatusAction::make(),
        ];
    }

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

        return trim(($order->channel?->name ?? '').' · recebido '.Fuso::formata($order->placed_at, 'd/m/Y \à\s H:i'));
    }
}
