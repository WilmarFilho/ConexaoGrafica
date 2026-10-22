<?php

namespace App\Filament\Resources\Chapters;

use App\Editorial\BookWorkflow;
use App\Editorial\EditorialException;
use App\Models\Chapter;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

/** Ações de situação do capítulo, compartilhadas pela lista, pela edição e pela etapa 6 do livro. */
class ChapterActions
{
    public static function submitForReview(): Action
    {
        return Action::make('enviar_revisao')
            ->label('Enviar para revisão')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('gray')
            ->visible(fn (Chapter $record) => $record->status === 'draft' && auth()->user()?->canEditEditorial())
            ->action(function (Chapter $record) {
                try {
                    BookWorkflow::submitChapterForReview($record);
                    Notification::make()->title('Capítulo enviado para revisão')->success()->send();
                } catch (EditorialException $e) {
                    Notification::make()->title('Não foi possível')->body($e->getMessage())->danger()->persistent()->send();
                }
            });
    }

    public static function approve(): Action
    {
        return Action::make('aprovar')
            ->label('Aprovar')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (Chapter $record) => $record->status !== 'approved' && auth()->user()?->canApproveEditorial())
            ->requiresConfirmation()
            ->modalHeading(fn (Chapter $record) => 'Aprovar o capítulo '.$record->sequence.'?')
            ->modalDescription('O resumo aprovado passa a ser o publicado; se estiver vazio, copia o original (ou a sugestão da IA).')
            ->action(function (Chapter $record) {
                try {
                    BookWorkflow::approveChapter($record);
                    Notification::make()->title('Capítulo aprovado')->success()->send();
                } catch (EditorialException $e) {
                    Notification::make()->title('Não foi possível')->body($e->getMessage())->danger()->persistent()->send();
                }
            });
    }

    public static function approveBulk(): BulkAction
    {
        return BulkAction::make('aprovar_lote')
            ->label('Aprovar selecionados')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn () => (bool) auth()->user()?->canApproveEditorial())
            ->requiresConfirmation()
            ->modalDescription('Cada capítulo é conferido (título, páginas, autores com ORCID ou instituição); os incompletos ficam como estão.')
            ->action(function (Collection $records) {
                $ok = 0;
                $failed = [];
                foreach ($records as $chapter) {
                    try {
                        BookWorkflow::approveChapter($chapter);
                        $ok++;
                    } catch (EditorialException $e) {
                        $failed[] = $chapter->sequence.': '.$e->getMessage();
                    }
                }

                Notification::make()
                    ->title("{$ok} capítulo(s) aprovado(s)")
                    ->body($failed ? 'Não aprovados — '.implode(' · ', $failed) : null)
                    ->{$failed ? 'warning' : 'success'}()
                    ->persistent()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
