<?php

namespace App\Filament\Resources\SyncLogs;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\SyncLogs\Pages\ListSyncLogs;
use App\Models\Order;
use App\Models\SyncLog;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Auditoria: tudo que entrou, saiu ou foi mudado à mão. Somente leitura.
 */
class SyncLogResource extends Resource
{
    protected static ?string $model = SyncLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Auditoria';

    protected static ?string $modelLabel = 'registro';

    protected static ?string $pluralModelLabel = 'registros';

    protected static ?string $slug = 'logs';

    protected static ?int $navigationSort = 80;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['channel', 'user', 'subject']))
            ->defaultSort('id', 'desc')
            ->paginated([50, 100, 200])
            ->poll('60s')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Quando')
                    ->dateTime('d/m/Y H:i:s')
                    ->extraAttributes(['class' => 'hub-mono'])
                    ->sortable(),

                TextColumn::make('direction')
                    ->label('Origem')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        SyncLog::IN => 'Canal → hub',
                        SyncLog::OUT => 'Hub → canal',
                        SyncLog::MANUAL => 'Manual',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        SyncLog::MANUAL => 'warning',
                        SyncLog::OUT => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('channel.name')
                    ->label('Canal')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('action_label')
                    ->label('Ação')
                    ->weight(FontWeight::Medium)
                    ->description(fn (SyncLog $record) => $record->level === 'error' ? 'erro' : null)
                    ->color(fn (SyncLog $record) => $record->level === 'error' ? 'danger' : null),

                TextColumn::make('subject_label')
                    ->label('Pedido')
                    ->state(fn (SyncLog $record) => $record->subject instanceof Order
                        ? '#'.($record->subject->external_number ?: $record->subject->external_id)
                        : null)
                    ->placeholder('—')
                    ->extraAttributes(['class' => 'hub-mono'])
                    ->url(fn (SyncLog $record) => $record->subject instanceof Order ? OrderResource::getUrl('view', ['record' => $record->subject]) : null),

                TextColumn::make('message')
                    ->label('Detalhe')
                    ->limit(110)
                    ->tooltip(fn (SyncLog $record) => $record->message)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('user.name')
                    ->label('Por')
                    ->placeholder('automático')
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('direction')
                    ->label('Origem')
                    ->options([SyncLog::MANUAL => 'Manual', SyncLog::IN => 'Canal → hub', SyncLog::OUT => 'Hub → canal']),
                SelectFilter::make('level')
                    ->label('Nível')
                    ->options(['info' => 'Informação', 'warning' => 'Aviso', 'error' => 'Erro']),
                SelectFilter::make('channel_id')
                    ->label('Canal')
                    ->relationship('channel', 'name')
                    ->preload(),
                SelectFilter::make('action')
                    ->label('Ação')
                    ->options(fn () => SyncLog::query()->distinct()->orderBy('action')->pluck('action', 'action')
                        ->mapWithKeys(fn ($a) => [$a => (new SyncLog(['action' => $a]))->action_label])->all()),
                Filter::make('periodo')
                    ->label('Período')
                    ->schema([
                        DatePicker::make('from')->label('De'),
                        DatePicker::make('until')->label('Até'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->filtersFormColumns(2)
            ->recordActions([])
            ->emptyStateHeading('Nada registrado ainda');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSyncLogs::route('/'),
        ];
    }
}
