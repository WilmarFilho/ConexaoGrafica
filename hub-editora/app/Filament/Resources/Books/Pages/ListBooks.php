<?php

namespace App\Filament\Resources\Books\Pages;

use App\Filament\Resources\Books\BookResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

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
            CreateAction::make()->label('Novo livro'),
        ];
    }
}
