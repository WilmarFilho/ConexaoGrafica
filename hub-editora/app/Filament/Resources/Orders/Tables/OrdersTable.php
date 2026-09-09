<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Central de Pedidos: uma linha por pedido, com o que a expedição precisa
 * ver de relance (origem, cliente, cidade, itens, valor, pagamento, etapa).
 */
class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['channel', 'customer', 'items', 'shipment']))
            ->defaultSort('placed_at', 'desc')
            ->striped(false)
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->poll('60s')
            ->recordUrl(fn (Order $record) => \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('external_number')
                    ->label('Pedido')
                    ->weight(FontWeight::SemiBold)
                    ->extraAttributes(['class' => 'hub-mono'])
                    ->formatStateUsing(fn (?string $state) => $state ? '#'.$state : '—')
                    ->searchable(['external_number', 'external_id'])
                    ->sortable(),

                TextColumn::make('channel.name')
                    ->label('Origem')
                    ->badge()
                    ->description(fn (Order $record) => $record->source_label)
                    ->color(fn (Order $record) => match ($record->channel?->slug) {
                        'woocommerce' => 'primary',
                        'pagarme' => 'success',
                        'amazon' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->weight(FontWeight::Medium)
                    ->description(fn (Order $record) => $record->ship_city_state ?? ($record->requires_shipping ? 'Sem endereço' : 'Digital'))
                    ->searchable(['customers.name', 'customers.email', 'customers.document'])
                    ->wrap(),

                TextColumn::make('items_summary')
                    ->label('Produtos')
                    ->limit(60)
                    ->tooltip(fn (Order $record) => $record->items_summary)
                    ->color('gray')
                    ->wrap(),

                TextColumn::make('total_cents')
                    ->label('Valor')
                    ->money('BRL', divideBy: 100)
                    ->extraAttributes(['class' => 'hub-mono'])
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('payment_status')
                    ->label('Pagamento')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'paid' => 'Pago',
                        'pending' => 'Pendente',
                        'refunded' => 'Estornado',
                        'failed' => 'Falhou',
                        default => $state ?? '—',
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'refunded', 'failed' => 'danger',
                        default => 'gray',
                    })
                    ->description(fn (Order $record) => self::paymentMethodLabel($record->payment_method)),

                TextColumn::make('status')
                    ->label('Expedição')
                    ->badge()
                    ->sortable(),

                TextColumn::make('shipment.tracking_code')
                    ->label('Rastreio')
                    ->extraAttributes(['class' => 'hub-mono'])
                    ->placeholder('—')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('placed_at')
                    ->label('Recebido')
                    ->since()
                    ->tooltip(fn (Order $record) => $record->placed_at?->format('d/m/Y H:i'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('channel_id')
                    ->label('Origem')
                    ->relationship('channel', 'name')
                    ->multiple()
                    ->preload(),

                SelectFilter::make('status')
                    ->label('Expedição')
                    ->options(OrderStatus::class)
                    ->multiple(),

                SelectFilter::make('payment_status')
                    ->label('Pagamento')
                    ->options([
                        'paid' => 'Pago',
                        'pending' => 'Pendente',
                        'refunded' => 'Estornado',
                        'failed' => 'Falhou',
                    ]),

                Filter::make('placed_at')
                    ->label('Período')
                    ->schema([
                        DatePicker::make('from')->label('De'),
                        DatePicker::make('until')->label('Até'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('placed_at', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('placed_at', '<=', $d)))
                    ->indicateUsing(function (array $data): array {
                        $out = [];
                        if ($data['from'] ?? null) {
                            $out[] = 'De '.\Carbon\Carbon::parse($data['from'])->format('d/m/Y');
                        }
                        if ($data['until'] ?? null) {
                            $out[] = 'Até '.\Carbon\Carbon::parse($data['until'])->format('d/m/Y');
                        }

                        return $out;
                    }),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                ViewAction::make()->label('Abrir'),
            ])
            ->emptyStateHeading('Nenhum pedido por aqui')
            ->emptyStateDescription('Os pedidos entram sozinhos pelos canais conectados. Rode "hub:sync" para importar agora.');
    }

    public static function paymentMethodLabel(?string $method): ?string
    {
        return match ($method) {
            null, '' => null,
            'pix' => 'Pix',
            'credit_card', 'woo-pagarme-payments-credit_card', 'woo-mercado-pago-custom' => 'Cartão',
            'boleto', 'woo-pagarme-payments-billet' => 'Boleto',
            'woo-pagarme-payments-pix' => 'Pix',
            default => ucfirst(str_replace(['woo-pagarme-payments-', '_', '-'], ['', ' ', ' '], $method)),
        };
    }
}
