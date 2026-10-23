<?php

namespace App\Filament\Resources\VendorCommissions;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\VendorCommissions\Pages\ListVendorCommissions;
use App\Filament\Resources\VendorPayouts\VendorPayoutResource;
use App\Models\Vendor;
use App\Models\VendorCommission;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Comissões de todos os vendedores: uma linha por pedido + livro. Somente
 * leitura; nascem e mudam com os pedidos (App\Vendors\Commissions).
 */
class VendorCommissionResource extends Resource
{
    protected static ?string $model = VendorCommission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Vendedores';

    protected static ?string $navigationLabel = 'Comissões';

    protected static ?string $modelLabel = 'comissão';

    protected static ?string $pluralModelLabel = 'comissões';

    protected static ?string $slug = 'comissoes';

    protected static ?int $navigationSort = 51;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Também usada na tela do vendedor e no portal do vendedor: lá sem a coluna
     * de vendedor e sem link para o pedido (o vendedor não vê dados de cliente).
     */
    public static function table(Table $table, bool $withVendor = true, bool $linkOrders = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['order.channel', 'vendor', 'payout']))
            ->defaultSort('earned_at', 'desc')
            ->paginated([25, 50, 100])
            ->columns(array_values(array_filter([
                TextColumn::make('earned_at')->label('Data')->date('d/m/Y')->sortable(),
                TextColumn::make('order.external_number')->label('Pedido')
                    ->extraAttributes(['class' => 'hub-mono'])
                    ->description(fn (VendorCommission $record) => $record->order?->channel?->name)
                    ->url(fn (VendorCommission $record) => $linkOrders && $record->order ? OrderResource::getUrl('view', ['record' => $record->order]) : null)
                    ->searchable(),
                $withVendor ? TextColumn::make('vendor.name')->label('Vendedor')->searchable()->sortable() : null,
                TextColumn::make('product_name')->label('Livro')->wrap()
                    ->description(fn (VendorCommission $record) => $record->kind === VendorCommission::REVERSAL ? 'Estorno: pedido caiu depois do repasse' : null)
                    ->searchable(),
                TextColumn::make('quantity')->label('Qtd.')->alignEnd(),
                TextColumn::make('base_cents')->label('Valor dos itens')
                    ->formatStateUsing(fn ($state) => Vendor::money((int) $state))
                    ->extraAttributes(['class' => 'hub-mono'])->alignEnd(),
                TextColumn::make('rate')->label('%')->alignEnd()->extraAttributes(['class' => 'hub-mono'])
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.').'%'),
                TextColumn::make('amount_cents')->label('Comissão')
                    ->formatStateUsing(fn ($state) => Vendor::money((int) $state))
                    ->extraAttributes(['class' => 'hub-mono'])->alignEnd()->weight('bold')
                    ->color(fn ($state) => (int) $state < 0 ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('status')->label('Situação')->badge()
                    ->formatStateUsing(fn (VendorCommission $record) => $record->statusLabel())
                    ->color(fn (VendorCommission $record) => $record->payout_id && $record->status === VendorCommission::AVAILABLE ? 'info' : VendorCommission::statusColor($record->status))
                    ->description(fn (VendorCommission $record) => $record->payout_id ? 'Repasse #'.$record->payout_id : null)
                    ->url(fn (VendorCommission $record) => $linkOrders && $record->payout_id ? VendorPayoutResource::getUrl('view', ['record' => $record->payout_id]) : null),
            ])))
            ->filters(array_values(array_filter([
                $withVendor ? SelectFilter::make('vendor_id')->label('Vendedor')->relationship('vendor', 'name')->searchable()->preload() : null,
                SelectFilter::make('status')->label('Situação')->options(VendorCommission::STATUSES)->multiple(),
                Filter::make('earned_at')
                    ->label('Período')
                    ->schema([
                        DatePicker::make('from')->label('De'),
                        DatePicker::make('until')->label('Até'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('earned_at', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('earned_at', '<=', $d))),
            ])))
            ->emptyStateHeading('Nenhuma comissão')
            ->emptyStateDescription('As comissões aparecem quando um pedido traz livro de um vendedor.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendorCommissions::route('/'),
        ];
    }
}
