<?php

namespace App\Filament\Resources\Books\Pages;

use App\Filament\Resources\Books\BookResource;
use App\Filament\Resources\Books\Schemas\BookWizard;
use App\Models\Book;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\HasWizard;
use Filament\Support\Icons\Heroicon;

/**
 * Mesmo assistente da criação, com as etapas livres (abas): a 6 traz os
 * capítulos e a 8 o checklist com as ações de situação. "Salvar" fica no
 * cabeçalho para gravar de qualquer etapa.
 */
class EditBook extends EditRecord
{
    use HasWizard;

    protected static string $resource = BookResource::class;

    public function getSteps(): array
    {
        return BookWizard::steps(editing: true);
    }

    protected function hasSkippableSteps(): bool
    {
        return true;
    }

    public function getTitle(): string
    {
        return $this->getRecord()->title ?: 'Cadastro de livro';
    }

    public function getSubheading(): ?string
    {
        /** @var Book $book */
        $book = $this->getRecord();

        return $book->statusLabel().' · DOI '.$book->doi.' ('.$book->doiStatusLabel().')';
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Livro salvo';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('salvar')
                ->label('Salvar')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->action(fn () => $this->save(shouldRedirect: false)),
            Action::make('ver_site')
                ->label('Ver página')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Book $record) => $record->landing_url, shouldOpenInNewTab: true)
                ->visible(fn (Book $record) => filled($record->landing_url)),
            ViewAction::make()->label('Resumo')->color('gray'),
            DeleteAction::make()->label('Excluir rascunho'),
        ];
    }
}
