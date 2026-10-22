<?php

namespace App\Filament\Resources\Chapters\Pages;

use App\Filament\Resources\Books\BookResource;
use App\Filament\Resources\Chapters\ChapterActions;
use App\Filament\Resources\Chapters\ChapterResource;
use App\Models\Chapter;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditChapter extends EditRecord
{
    protected static string $resource = ChapterResource::class;

    public function getTitle(): string
    {
        /** @var Chapter $c */
        $c = $this->getRecord();

        return 'Capítulo '.$c->sequence.' · '.$c->title;
    }

    public function getSubheading(): ?string
    {
        /** @var Chapter $c */
        $c = $this->getRecord();

        return ($c->book?->title ?? '').' · '.$c->statusLabel().' · DOI '.$c->doi;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Capítulo salvo';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('livro')
                ->label('Abrir livro')
                ->icon(Heroicon::OutlinedBookOpen)
                ->color('gray')
                ->url(fn (Chapter $record) => BookResource::getUrl('edit', ['record' => $record->book_id])),
            ChapterActions::submitForReview(),
            ChapterActions::approve(),
            DeleteAction::make()->label('Remover'),
        ];
    }
}
