<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('channelRefs'))
            ->defaultSort('name')
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->extraAttributes(['class' => 'hub-mono'])
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Título')
                    ->weight(FontWeight::Medium)
                    ->description(fn (Product $record) => $record->isbn ? 'ISBN '.$record->isbn : null)
                    ->searchable(['name', 'isbn'])
                    ->sortable()
                    ->wrap(),

                IconColumn::make('physical')
                    ->label('Tipo')
                    ->icon(fn (bool $state) => $state ? Heroicon::OutlinedCube : Heroicon::OutlinedDevicePhoneMobile)
                    ->color(fn (bool $state) => $state ? 'primary' : 'gray')
                    ->tooltip(fn (bool $state) => $state ? 'Físico' : 'E-book'),

                TextColumn::make('weight_grams')
                    ->label('Peso')
                    ->formatStateUsing(fn (?int $state) => $state ? $state.' g' : null)
                    ->placeholder('—')
                    ->extraAttributes(['class' => 'hub-mono'])
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('dimensions_label')
                    ->label('Medidas')
                    ->placeholder('—')
                    ->extraAttributes(['class' => 'hub-mono']),

                IconColumn::make('shipping_ready')
                    ->label('Frete')
                    ->state(fn (Product $record) => ! $record->physical || $record->hasShippingDimensions())
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedExclamationTriangle)
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn (Product $record) => ! $record->physical ? 'E-book: sem frete' : ($record->hasShippingDimensions() ? 'Cotação exata' : 'Usará o pacote padrão')),

                TextColumn::make('stock_physical')
                    ->label('Estoque')
                    ->extraAttributes(['class' => 'hub-mono'])
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('channel_refs_count')
                    ->label('Canais')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                ToggleColumn::make('active')
                    ->label('Ativo'),
            ])
            ->filters([
                TernaryFilter::make('physical')
                    ->label('Tipo')
                    ->trueLabel('Físicos')
                    ->falseLabel('E-books'),

                Filter::make('sem_medidas')
                    ->label('Sem peso ou medidas')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where('physical', true)
                        ->where(fn ($w) => $w->whereNull('weight_grams')->orWhereNull('width_cm')->orWhereNull('height_cm')->orWhereNull('depth_cm'))),

                TernaryFilter::make('active')->label('Ativo'),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
            ])
            ->toolbarActions([
                BulkAction::make('medidas')
                    ->label('Definir peso e medidas')
                    ->icon(Heroicon::OutlinedScale)
                    ->modalHeading('Aplicar peso e medidas aos selecionados')
                    ->modalDescription('Deixe um campo vazio para não alterar aquele valor.')
                    ->schema([
                        TextInput::make('weight_grams')->label('Peso (g)')->numeric()->minValue(1)->suffix('g'),
                        TextInput::make('width_cm')->label('Largura')->numeric()->minValue(1)->suffix('cm'),
                        TextInput::make('height_cm')->label('Altura')->numeric()->minValue(1)->suffix('cm'),
                        TextInput::make('depth_cm')->label('Espessura')->numeric()->minValue(1)->suffix('cm'),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $values = array_filter($data, fn ($v) => $v !== null && $v !== '');
                        if (! $values) {
                            Notification::make()->title('Nada para aplicar')->warning()->send();

                            return;
                        }
                        $records->each(fn (Product $p) => $p->update($values));
                        Notification::make()->title($records->count().' produto(s) atualizado(s)')->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading('Nenhum produto ainda')
            ->emptyStateDescription('Rode "hub:sync-products woocommerce" para trazer o catálogo da loja.');
    }
}
