<?php

namespace App\Filament\Resources\Books\Pages;

use App\Filament\Resources\Books\BookResource;
use App\Filament\Resources\Books\Schemas\BookWizard;
use App\Models\SyncLog;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;

/**
 * Assistente de 8 etapas (linear na criação). Ao concluir, o livro nasce
 * como rascunho e o editor segue para a edição, onde entram os capítulos.
 */
class CreateBook extends CreateRecord
{
    use HasWizard;

    protected static string $resource = BookResource::class;

    protected static ?string $title = 'Cadastro de livro';

    public function getSteps(): array
    {
        return BookWizard::steps(editing: false);
    }

    protected function afterCreate(): void
    {
        SyncLog::record(null, SyncLog::MANUAL, 'editorial.book_created', $this->getRecord(), 'Livro "'.$this->getRecord()->title.'" cadastrado como rascunho');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Livro salvo como rascunho. Agora cadastre os capítulos (etapa 6).';
    }

    protected function getRedirectUrl(): string
    {
        return BookResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
