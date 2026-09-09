<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Integrations\WooCommerce\ImportProducts;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Throwable;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected static ?string $title = 'Produtos';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sincronizar')
                ->label('Atualizar da loja')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Atualizar catálogo a partir do WooCommerce')
                ->modalDescription('Traz títulos novos e atualiza peso, medidas e estoque que a loja tiver preenchido. O que só existe aqui no hub é mantido.')
                ->action(function () {
                    try {
                        $r = ImportProducts::make()->all();
                        Notification::make()
                            ->title("{$r['products']} produto(s) sincronizado(s)")
                            ->body($r['relinked'] ? "{$r['relinked']} item(ns) de pedido religado(s)." : null)
                            ->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Falha ao sincronizar')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
            CreateAction::make()->label('Novo produto'),
        ];
    }
}
