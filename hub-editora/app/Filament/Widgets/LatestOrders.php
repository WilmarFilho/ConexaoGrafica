<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestOrders extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 8;

    protected static ?string $heading = 'Últimos pedidos';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Order::query()->with(['channel', 'customer', 'items'])->latest('placed_at')->limit(8))
            ->paginated(false)
            ->recordUrl(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('external_number')
                    ->label('Pedido')
                    ->weight(FontWeight::SemiBold)
                    ->extraAttributes(['class' => 'hub-mono'])
                    ->formatStateUsing(fn (?string $state) => $state ? '#'.$state : '—')
                    ->description(fn (Order $record) => trim(($record->channel?->name ?? '').($record->source_label ? ' · '.$record->source_label : ''))),
                TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->description(fn (Order $record) => $record->ship_city_state ?? ($record->requires_shipping ? null : 'Digital'))
                    ->wrap(),
                TextColumn::make('items_summary')->label('Itens')->limit(40)->color('gray')->wrap(),
                TextColumn::make('total_cents')->label('Valor')->money('BRL', divideBy: 100)->extraAttributes(['class' => 'hub-mono'])->alignEnd(),
                TextColumn::make('status')->label('Etapa')->badge(),
                TextColumn::make('placed_at')->label('Recebido')->since(),
            ]);
    }
}
