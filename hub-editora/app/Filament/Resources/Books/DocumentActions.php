<?php

namespace App\Filament\Resources\Books;

use App\Editorial\Documents\PublishSheets;
use App\Editorial\Documents\SheetGenerator;
use App\Editorial\WordPress\Client;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Document;
use App\Models\SyncLog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Throwable;

/**
 * Fichas científicas em PDF (Conexão DOI, frente E): download imediato no
 * livro e no capítulo, "Regenerar e publicar" no livro e a coluna de
 * situação nas listas.
 */
class DocumentActions
{
    /** Gera (se mudou) e baixa a ficha do livro. */
    public static function downloadBookSheet(): Action
    {
        return Action::make('ficha_pdf')
            ->label('Gerar ficha PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->visible(fn () => (bool) auth()->user()?->canAccessEditorial())
            ->action(function (Book $record) {
                try {
                    $doc = app(SheetGenerator::class)->forBook($record->fresh());
                } catch (Throwable $e) {
                    Notification::make()->title('Não foi possível gerar a ficha')->body($e->getMessage())->danger()->persistent()->send();

                    return null;
                }

                return response()->download($doc->absolutePath(), SheetGenerator::downloadName($doc), ['Content-Type' => 'application/pdf']);
            });
    }

    /** Gera (se mudou) e baixa a ficha do capítulo. */
    public static function downloadChapterSheet(): Action
    {
        return Action::make('ficha_pdf')
            ->label('Gerar ficha PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->visible(fn () => (bool) auth()->user()?->canAccessEditorial())
            ->action(function (Chapter $record) {
                try {
                    $doc = app(SheetGenerator::class)->forChapter($record->fresh());
                } catch (Throwable $e) {
                    Notification::make()->title('Não foi possível gerar a ficha')->body($e->getMessage())->danger()->persistent()->send();

                    return null;
                }

                return response()->download($doc->absolutePath(), SheetGenerator::downloadName($doc), ['Content-Type' => 'application/pdf']);
            });
    }

    /** Regenera todas as fichas do livro e reenvia ao site pela fila. */
    public static function publishSheets(): Action
    {
        return Action::make('publicar_fichas')
            ->label('Regenerar e publicar fichas')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->visible(fn (Book $record) => $record->isPublished() && filled($record->landing_url) && auth()->user()?->canEditEditorial())
            ->requiresConfirmation()
            ->modalHeading('Regenerar e publicar as fichas?')
            ->modalDescription('A ficha do livro e a de cada capítulo aprovado são geradas de novo e enviadas ao site pela fila, substituindo as anteriores.')
            ->action(function (Book $record) {
                if (! Client::isConfigured()) {
                    Notification::make()->title('Site não configurado')->body('Defina CONEXAO_DOI_WP_URL e CONEXAO_DOI_WP_SECRET no .env do Hub.')->danger()->persistent()->send();

                    return;
                }

                PublishSheets::dispatch($record->id, true, auth()->id());
                SyncLog::record(null, SyncLog::MANUAL, 'document.requested', $record, 'Regeneração e publicação das fichas solicitada');
                Notification::make()->title('Fichas enfileiradas')->body('Geração e envio ao site em andamento.')->success()->send();
            });
    }

    /** Coluna de situação da ficha (livros e capítulos). */
    public static function column(): IconColumn
    {
        return IconColumn::make('sheet_state')
            ->label('Ficha')
            ->state(fn (Book|Chapter $record) => ($record->sheet ?? new Document)->state())
            ->icon(fn (string $state) => match ($state) {
                'published' => Heroicon::OutlinedDocumentCheck,
                'stale' => Heroicon::OutlinedExclamationTriangle,
                'unpublished' => Heroicon::OutlinedDocument,
                default => Heroicon::OutlinedDocumentMinus,
            })
            ->color(fn (string $state) => match ($state) {
                'published' => 'success',
                'stale' => 'warning',
                'unpublished' => 'info',
                default => 'gray',
            })
            ->tooltip(fn (string $state, Book|Chapter $record) => Document::stateLabel($state)
                .(($record->sheet?->generated_at) ? ' · gerada em '.$record->sheet->generated_at->format('d/m/Y H:i') : ''))
            ->url(fn (Book|Chapter $record) => $record->sheet?->public_url, shouldOpenInNewTab: true)
            ->alignCenter();
    }
}
