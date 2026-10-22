<?php

namespace App\Filament\Resources\Books\Pages;

use App\Filament\Resources\Books\BookResource;
use App\Filament\Resources\Imports\ImportResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListBooks extends ListRecords
{
    protected static string $resource = BookResource::class;

    protected static ?string $title = 'Livros';

    public function getSubheading(): ?string
    {
        return 'Obras com DOI e página pública no site. Rascunho → revisão → aprovado → publicado.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importar_pdf')
                ->label('Importar PDF')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->visible(fn () => ImportResource::canAccess())
                ->url(ImportResource::getUrl('create')),
            CreateAction::make()->label('Novo livro'),
        ];
    }
}
