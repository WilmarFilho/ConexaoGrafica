<?php

namespace App\Filament\Resources\Books\RelationManagers;

use App\Editorial\BookWorkflow;
use App\Editorial\ChapterImporter;
use App\Editorial\EditorialException;
use App\Filament\Resources\Chapters\ChapterActions;
use App\Filament\Resources\Chapters\Schemas\ChapterForm;
use App\Filament\Resources\Chapters\Tables\ChaptersTable;
use App\Filament\Resources\Imports\ImportResource;
use App\Models\Book;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Etapa 6 do assistente: capítulos do livro, com ordenação por arrastar,
 * páginas, autores, "Importar JSON" (lista no formato do contrato) e
 * aprovação individual ou em lote (revisor).
 */
class ChaptersRelationManager extends RelationManager
{
    protected static string $relationship = 'chapters';

    protected static ?string $title = 'Capítulos';

    protected static ?string $modelLabel = 'capítulo';

    protected static ?string $pluralModelLabel = 'capítulos';

    public function form(Schema $schema): Schema
    {
        return ChapterForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        $canEdit = (bool) auth()->user()?->canEditEditorial();

        return ChaptersTable::configure($table, withBook: false)
            ->reorderable('sequence', $canEdit)
            ->headerActions([
                CreateAction::make()
                    ->label('Adicionar capítulo')
                    ->icon(Heroicon::OutlinedPlus)
                    ->modalHeading('Novo capítulo')
                    ->modalWidth('5xl')
                    ->visible($canEdit),

                Action::make('importar_json')
                    ->label('Importar JSON')
                    ->icon(Heroicon::OutlinedDocumentArrowUp)
                    ->color('gray')
                    ->visible($canEdit)
                    ->modalHeading('Importar capítulos por JSON')
                    ->modalDescription('Cole uma lista de capítulos no formato do contrato (os mesmos campos de chapters[] do payload: title, number, section, first_page, last_page, abstract, keywords, references e contributors com name, orcid, role e affiliation). Pessoas e instituições que faltarem são criadas.')
                    ->modalWidth('4xl')
                    ->schema([
                        Textarea::make('json')->label('JSON')->required()->rows(16)
                            ->placeholder('[{"sequence": 1, "number": "1", "title": "…", "first_page": 21, "last_page": 34, "contributors": [{"name": "…", "orcid": null, "role": "author", "affiliation": {"name": "…"}}]}]')
                            ->extraInputAttributes(['class' => 'hub-mono']),
                    ])
                    ->action(function (array $data) {
                        /** @var Book $book */
                        $book = $this->getOwnerRecord();

                        try {
                            $record = ChapterImporter::import($book, $data['json']);
                            Notification::make()
                                ->title("{$record['chapters']} capítulo(s) importado(s)")
                                ->body(($record['people'] ? "{$record['people']} pessoa(s)" : '').($record['institutions'] ? " e {$record['institutions']} instituição(ões)" : '').($record['people'] || $record['institutions'] ? ' criada(s).' : ''))
                                ->success()->send();
                        } catch (EditorialException $e) {
                            Notification::make()->title('Importação não feita')->body($e->getMessage())->danger()->persistent()->send();
                        }
                    }),

                Action::make('importar_pdf')
                    ->label('Importar PDF')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->color('gray')
                    ->visible(fn () => $canEdit && ImportResource::canAccess())
                    ->url(fn () => ImportResource::getUrl('create', ['book' => $this->getOwnerRecord()->getKey()])),

                Action::make('renumerar')
                    ->label('Renumerar sufixos DOI')
                    ->icon(Heroicon::OutlinedHashtag)
                    ->color('gray')
                    ->visible($canEdit)
                    ->requiresConfirmation()
                    ->modalDescription('Recalcula o sufixo ({sufixo do livro}.{NN}) pela ordem atual, só nos capítulos ainda sem DOI validado.')
                    ->action(function () {
                        $n = BookWorkflow::renumberChapterSuffixes($this->getOwnerRecord());
                        Notification::make()->title("{$n} capítulo(s) renumerado(s)")->success()->send();
                    }),
            ])
            ->recordActions([
                EditAction::make()->label('Editar')->modalWidth('5xl')->visible($canEdit),
                ChapterActions::approve(),
                DeleteAction::make()->label('Remover')
                    ->visible(fn ($record) => $canEdit && $record->doi_status === 'none'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ChapterActions::approveBulk(),
                    DeleteBulkAction::make()->label('Remover selecionados')->visible($canEdit),
                ]),
            ])
            ->emptyStateHeading('Nenhum capítulo ainda')
            ->emptyStateDescription('Adicione um a um, importe por JSON ou use a importação com IA.');
    }
}
