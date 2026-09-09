<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        $def = config('hub.melhor_envio.default_package');

        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)
                    ->columnSpan(2)
                    ->schema([
                        Section::make('Identificação')
                            ->columns(2)
                            ->schema([
                                TextInput::make('name')->label('Título')->required()->maxLength(255)->columnSpanFull(),
                                TextInput::make('sku')->label('SKU interno')->required()->maxLength(60)->unique(ignoreRecord: true)
                                    ->helperText('Vem da loja; só mude se souber o que está fazendo.'),
                                TextInput::make('isbn')->label('ISBN')->maxLength(20),
                            ]),

                        Section::make('Expedição')
                            ->description("Usado na cotação do frete. Sem preencher, o hub assume o pacote padrão: {$def['weight_grams']} g, {$def['width_cm']} × {$def['height_cm']} × {$def['depth_cm']} cm.")
                            ->columns(4)
                            ->schema([
                                TextInput::make('weight_grams')->label('Peso (g)')->numeric()->minValue(1)->maxValue(30000)->suffix('g'),
                                TextInput::make('width_cm')->label('Largura')->numeric()->minValue(1)->maxValue(100)->suffix('cm'),
                                TextInput::make('height_cm')->label('Altura')->numeric()->minValue(1)->maxValue(100)->suffix('cm'),
                                TextInput::make('depth_cm')->label('Espessura')->numeric()->minValue(1)->maxValue(100)->suffix('cm'),
                            ]),
                    ]),

                Grid::make(1)
                    ->columnSpan(1)
                    ->schema([
                        Section::make('Situação')
                            ->schema([
                                Toggle::make('physical')->label('Produto físico')->helperText('Desligado = e-book, não entra na expedição.')->default(true),
                                Toggle::make('active')->label('Ativo')->default(true),
                                TextInput::make('stock_physical')->label('Estoque físico')->numeric()->default(0)
                                    ->helperText('Atualizado pela loja quando ela controla estoque.'),
                            ]),

                        Section::make('Nos canais')
                            ->schema([
                                TextEntry::make('channel_refs_label')
                                    ->hiddenLabel()
                                    ->state(fn (?Product $record) => $record?->channelRefs()->with('channel')->get()
                                        ->map(fn ($r) => ($r->channel?->name ?? '?').': '.$r->external_id.($r->external_sku ? " (SKU {$r->external_sku})" : ''))
                                        ->implode("\n") ?: null)
                                    ->placeholder('Ainda não vinculado a nenhum canal.')
                                    ->extraAttributes(['class' => 'hub-mono', 'style' => 'white-space: pre-line']),
                            ])
                            ->hiddenOn('create'),
                    ]),
            ]);
    }
}
