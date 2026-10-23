<?php

namespace App\Filament\Resources\VendorPayouts;

use App\Filament\Resources\VendorPayouts\Pages\ListVendorPayouts;
use App\Filament\Resources\VendorPayouts\Pages\ViewVendorPayout;
use App\Filament\Resources\VendorPayouts\RelationManagers\PayoutCommissionsRelationManager;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\Vendor;
use App\Models\VendorPayout;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Repasses aos vendedores: gerar, registrar o pagamento, cancelar. */
class VendorPayoutResource extends Resource
{
    protected static ?string $model = VendorPayout::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Vendedores';

    protected static ?string $navigationLabel = 'Repasses';

    protected static ?string $modelLabel = 'repasse';

    protected static ?string $pluralModelLabel = 'repasses';

    protected static ?string $slug = 'repasses';

    protected static ?int $navigationSort = 52;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function canCreate(): bool
    {
        return false; // nasce pela ação "Gerar repasse"
    }

    public static function getNavigationBadge(): ?string
    {
        $n = VendorPayout::query()->where('status', VendorPayout::DRAFT)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Repasses a pagar';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Repasse')
                    ->columnSpan(2)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('vendor.name')->label('Vendedor')->weight(FontWeight::Medium)
                            ->url(fn (VendorPayout $record) => VendorResource::getUrl('edit', ['record' => $record->vendor_id])),
                        TextEntry::make('amount_cents')->label('Valor')->weight(FontWeight::Bold)
                            ->formatStateUsing(fn ($state) => Vendor::money((int) $state)),
                        TextEntry::make('status')->label('Situação')->badge()
                            ->formatStateUsing(fn (VendorPayout $record) => $record->statusLabel())
                            ->color(fn (VendorPayout $record) => VendorPayout::statusColor($record->status)),
                        TextEntry::make('period_until')->label('Comissões até')->date('d/m/Y'),
                        TextEntry::make('commissions_count')->label('Comissões'),
                        TextEntry::make('creator.name')->label('Gerado por')->placeholder('—')
                            ->helperText(fn (VendorPayout $record) => $record->created_at?->format('d/m/Y H:i')),
                    ]),
                Section::make('Pagamento')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('paid_at')->label('Pago em')->dateTime('d/m/Y H:i')->placeholder('Ainda não pago'),
                        TextEntry::make('method')->label('Forma')->formatStateUsing(fn (?string $state) => VendorPayout::METHODS[$state] ?? $state)->placeholder('—'),
                        TextEntry::make('reference')->label('Identificador')->copyable()->placeholder('—'),
                        TextEntry::make('payer.name')->label('Registrado por')->placeholder('—'),
                        TextEntry::make('vendor_destino')->label('Dados do vendedor')
                            ->state(fn (VendorPayout $record) => trim(implode("\n", array_filter([
                                $record->vendor?->legal_name, $record->vendor?->document_label,
                                Vendor::PAYOUT_METHODS[$record->vendor?->payout_method] ?? null, $record->vendor?->payout_details,
                            ]))) ?: null)
                            ->placeholder('Sem dados de repasse no cadastro do vendedor.')
                            ->extraAttributes(['style' => 'white-space: pre-line'])
                            ->visible(fn (VendorPayout $record) => $record->status === VendorPayout::DRAFT),
                        TextEntry::make('notes')->label('Observações')->placeholder('—'),
                    ]),
            ]);
    }

    public static function table(Table $table, bool $withVendor = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('vendor'))
            ->defaultSort('id', 'desc')
            ->columns(array_values(array_filter([
                TextColumn::make('id')->label('#')->extraAttributes(['class' => 'hub-mono']),
                $withVendor ? TextColumn::make('vendor.name')->label('Vendedor')->searchable()->sortable() : null,
                TextColumn::make('period_until')->label('Comissões até')->date('d/m/Y')->sortable(),
                TextColumn::make('commissions_count')->label('Comissões')->alignEnd(),
                TextColumn::make('amount_cents')->label('Valor')
                    ->formatStateUsing(fn ($state) => Vendor::money((int) $state))
                    ->extraAttributes(['class' => 'hub-mono'])->alignEnd()->weight('bold')->sortable(),
                TextColumn::make('status')->label('Situação')->badge()
                    ->formatStateUsing(fn (VendorPayout $record) => $record->statusLabel())
                    ->color(fn (VendorPayout $record) => VendorPayout::statusColor($record->status)),
                TextColumn::make('paid_at')->label('Pago em')->dateTime('d/m/Y')->placeholder('—')->sortable(),
            ])))
            ->filters(array_values(array_filter([
                $withVendor ? SelectFilter::make('vendor_id')->label('Vendedor')->relationship('vendor', 'name')->searchable()->preload() : null,
                SelectFilter::make('status')->label('Situação')->options(VendorPayout::STATUSES),
            ])))
            ->recordUrl(fn (VendorPayout $record) => static::canAccess() ? static::getUrl('view', ['record' => $record]) : null)
            ->recordActions(static::canAccess() ? [ViewAction::make()->label('Abrir')] : [])
            ->emptyStateHeading('Nenhum repasse')
            ->emptyStateDescription('Gere um repasse quando houver comissões a repassar.');
    }

    public static function getRelations(): array
    {
        return [
            PayoutCommissionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendorPayouts::route('/'),
            'view' => ViewVendorPayout::route('/{record}'),
        ];
    }
}
