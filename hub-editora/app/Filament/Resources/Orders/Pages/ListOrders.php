<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected static ?string $title = 'Central de Pedidos';

    public function getTabs(): array
    {
        return [
            'todos' => Tab::make('Todos'),

            'a_expedir' => Tab::make('A expedir')
                ->badge(fn () => Order::query()->awaitingShipment()->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->awaitingShipment()),

            'aguardando_pagamento' => Tab::make('Aguardando pagamento')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [OrderStatus::New, OrderStatus::AwaitingPayment])),

            'em_transito' => Tab::make('Em trânsito')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [OrderStatus::LabelGenerated, OrderStatus::ReadyToShip, OrderStatus::Shipped])),

            'problemas' => Tab::make('Problemas')
                ->badge(fn () => Order::query()->where('status', OrderStatus::Problem)->count() ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OrderStatus::Problem)),

            'digitais' => Tab::make('Digitais')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('requires_shipping', false)),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'todos';
    }
}
