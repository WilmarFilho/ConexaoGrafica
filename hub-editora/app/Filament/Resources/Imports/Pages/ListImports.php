<?php

namespace App\Filament\Resources\Imports\Pages;

use App\Filament\Resources\Imports\ImportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListImports extends ListRecords
{
    protected static string $resource = ImportResource::class;

    protected static ?string $title = 'Importações com IA';

    public function getSubheading(): ?string
    {
        return 'PDF → proposta da IA → revisão lado a lado → integração ao catálogo. Modelo '.config('hub.ai.model').
            ((string) config('hub.ai.key') === '' ? ' (OPENAI_API_KEY não configurada: novas importações ficam em erro).' : '.');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Importar PDF')->icon(Heroicon::OutlinedDocumentArrowUp),
        ];
    }
}
