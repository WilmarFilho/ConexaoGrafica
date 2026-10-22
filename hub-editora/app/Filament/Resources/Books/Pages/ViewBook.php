<?php

namespace App\Filament\Resources\Books\Pages;

use App\Filament\Resources\Books\BookActions;
use App\Filament\Resources\Books\BookResource;
use App\Models\Book;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * Resumo do livro: situação, identificadores, pessoas, checklist e payload.
 * É a tela do perfil Depósito e o lugar das ações de depósito (frente D).
 */
class ViewBook extends ViewRecord
{
    protected static string $resource = BookResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->title;
    }

    public function getSubheading(): ?string
    {
        /** @var Book $book */
        $book = $this->getRecord();

        return $book->statusLabel().' · DOI '.$book->doi.' ('.$book->doiStatusLabel().')';
    }

    protected function getHeaderActions(): array
    {
        $deposit = auth()->user()?->canDeposit() ? BookResource::depositActions() : [];

        return [
            EditAction::make()->label('Editar'),
            Action::make('ver_site')
                ->label('Ver página')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Book $record) => $record->landing_url, shouldOpenInNewTab: true)
                ->visible(fn (Book $record) => filled($record->landing_url)),
            ...BookActions::transitions(),
            ...$deposit,
        ];
    }
}
