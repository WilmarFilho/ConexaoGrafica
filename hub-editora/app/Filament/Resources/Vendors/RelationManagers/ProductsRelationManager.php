<?php

namespace App\Filament\Resources\Vendors\RelationManagers;

use App\Models\Product;
use App\Models\Vendor;
use Filament\Actions\AssociateAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Livros do vendedor. Ligar ou desligar um livro, ou mudar o % dele, refaz as
 * comissões ainda não repassadas dos pedidos desse livro (Product::booted).
 */
class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    protected static ?string $title = 'Livros';

    protected static ?string $modelLabel = 'livro';

    protected static ?string $pluralModelLabel = 'livros';

    protected static bool $isLazy = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('vendor_commission_pct')->label('% próprio deste livro')
                ->numeric()->minValue(0)->maxValue(100)->step(0.01)->suffix('%')
                ->helperText('Vazio: usa o % do vendedor.'),
        ]);
    }

    public function table(Table $table): Table
    {
        /** @var Vendor $vendor */
        $vendor = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->columns([
                TextColumn::make('sku')->label('SKU')->extraAttributes(['class' => 'hub-mono']),
                TextColumn::make('name')->label('Título')->weight(FontWeight::Medium)->wrap()
                    ->description(fn (Product $record) => $record->isbn ? 'ISBN '.$record->isbn : null),
                TextColumn::make('comissao')->label('Comissão')
                    ->state(fn (Product $record) => number_format((float) ($record->vendor_commission_pct ?? $vendor->commission_pct), 2, ',', '.').'%')
                    ->description(fn (Product $record) => $record->vendor_commission_pct !== null ? 'próprio do livro' : 'padrão do vendedor')
                    ->extraAttributes(['class' => 'hub-mono']),
            ])
            ->headerActions([
                AssociateAction::make()->label('Ligar livro')
                    ->recordSelectSearchColumns(['name', 'sku', 'isbn'])
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query->whereNull('vendor_id'))
                    ->preloadRecordSelect(),
            ])
            ->recordActions([
                EditAction::make()->label('% do livro')->modalHeading('Comissão deste livro'),
                DissociateAction::make()->label('Desligar')
                    ->modalDescription('As comissões ainda não repassadas deste livro deixam de existir; as já repassadas ficam e, se preciso, geram estorno.'),
            ])
            ->emptyStateHeading('Nenhum livro ligado')
            ->emptyStateDescription('Use "Ligar livro" para escolher os produtos do catálogo que são deste vendedor.');
    }
}
