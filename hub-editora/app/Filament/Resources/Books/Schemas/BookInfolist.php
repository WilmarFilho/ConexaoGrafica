<?php

namespace App\Filament\Resources\Books\Schemas;

use App\Filament\Resources\Chapters\ChapterResource;
use App\Models\Book;
use App\Models\Contribution;
use App\Models\Document;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\HtmlString;

/**
 * Tela de resumo do livro (somente leitura): o que o perfil Depósito vê e
 * onde a frente D pendura as ações de depósito.
 */
class BookInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)
                    ->columnSpan(2)
                    ->schema([
                        Section::make('Obra')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('title')->label('Título')->weight(FontWeight::SemiBold)->columnSpanFull(),
                                TextEntry::make('subtitle')->label('Subtítulo')->placeholder('—')->columnSpanFull(),
                                TextEntry::make('type')->label('Tipo')->formatStateUsing(fn (string $state) => Book::TYPES[$state] ?? $state),
                                TextEntry::make('edition_number')->label('Edição')->placeholder('—'),
                                TextEntry::make('publisher')->label('Editora'),
                                TextEntry::make('publication_place')->label('Local'),
                                TextEntry::make('publication_year')->label('Ano'),
                                TextEntry::make('publication_date')->label('Data')->date('d/m/Y')->placeholder('—'),
                                TextEntry::make('pages')->label('Páginas')->placeholder('—'),
                                TextEntry::make('format')->label('Formato')->formatStateUsing(fn (string $state) => Book::FORMATS[$state] ?? $state),
                                TextEntry::make('subject_area')->label('Área')->formatStateUsing(fn (string $state, Book $record) => $state.($record->subject_subarea ? ' · '.$record->subject_subarea : '')),
                                TextEntry::make('language')->label('Idioma')->formatStateUsing(fn (string $state) => Book::LANGUAGES[$state] ?? $state),
                            ]),

                        Section::make('Organizadores e autores')
                            ->schema([
                                RepeatableEntry::make('contributions')
                                    ->hiddenLabel()
                                    ->columns(4)
                                    ->schema([
                                        TextEntry::make('person.name')->label('Nome')->weight(FontWeight::Medium)->columnSpan(2),
                                        TextEntry::make('role')->label('Papel')->formatStateUsing(fn (string $state) => Contribution::ROLES[$state] ?? $state),
                                        TextEntry::make('affiliation_label')->label('Afiliação')
                                            ->state(fn (Contribution $record) => $record->affiliation()['name'] ?? null)
                                            ->placeholder('—'),
                                    ])
                                    ->placeholder('Nenhuma pessoa vinculada.'),
                            ]),

                        Section::make('Resumo e palavras-chave')
                            ->collapsed()
                            ->schema([
                                TextEntry::make('abstract')->hiddenLabel(),
                                TextEntry::make('keywords')->label('Palavras-chave')->badge()->color('gray')->placeholder('—'),
                            ]),

                        Section::make('Prévia do payload enviado ao site')
                            ->collapsed()
                            ->schema([
                                Text::make(fn (Book $record) => new HtmlString(BookWizard::payloadHtml($record))),
                            ]),
                    ]),

                Grid::make(1)
                    ->columnSpan(1)
                    ->schema([
                        Section::make('Situação')
                            ->schema([
                                TextEntry::make('status')->label('Editorial')->badge()
                                    ->formatStateUsing(fn (string $state) => Book::STATUSES[$state] ?? $state)
                                    ->color(fn (string $state) => Book::statusColor($state)),
                                TextEntry::make('doi_status')->label('DOI')->badge()
                                    ->formatStateUsing(fn (string $state) => Book::DOI_STATUSES[$state] ?? $state)
                                    ->color(fn (string $state) => Book::doiStatusColor($state)),
                                TextEntry::make('approver.name')->label('Aprovado por')->placeholder('—'),
                                TextEntry::make('approved_at')->label('Aprovado em')->dateTime('d/m/Y H:i')->placeholder('—'),
                                TextEntry::make('published_at')->label('Publicado em')->dateTime('d/m/Y H:i')->placeholder('—'),
                            ]),

                        Section::make('Identificadores')
                            ->schema([
                                TextEntry::make('doi')->label('DOI')->copyable()->extraAttributes(['class' => 'hub-mono'])
                                    ->url(fn (Book $record) => $record->doi_url, shouldOpenInNewTab: true),
                                TextEntry::make('isbn_print')->label('ISBN impresso')->placeholder('—')->extraAttributes(['class' => 'hub-mono']),
                                TextEntry::make('isbn_ebook')->label('ISBN e-book')->placeholder('—')->extraAttributes(['class' => 'hub-mono']),
                                TextEntry::make('slug')->label('Slug')->extraAttributes(['class' => 'hub-mono']),
                                TextEntry::make('landing_url')->label('Página pública')->placeholder('ainda não publicada')
                                    ->url(fn (?string $state) => $state, shouldOpenInNewTab: true),
                                TextEntry::make('wp_post_id')->label('Post no WP')->placeholder('—')->extraAttributes(['class' => 'hub-mono']),
                            ]),

                        Section::make('Capítulos')
                            ->schema([
                                TextEntry::make('chapters_count')->hiddenLabel()
                                    ->state(fn (Book $record) => $record->chapters()->count().' capítulo(s) · '.$record->chapters()->where('status', 'approved')->count().' aprovado(s)')
                                    ->url(fn (Book $record) => ChapterResource::getUrl('index', ['tableFilters' => ['book_id' => ['value' => $record->id]]])),
                            ]),

                        Section::make('Ficha PDF')
                            ->schema([
                                TextEntry::make('sheet_state')->hiddenLabel()
                                    ->state(fn (Book $record) => Document::stateLabel(($record->sheet ?? new Document)->state()))
                                    ->badge()
                                    ->color(fn (Book $record) => match (($record->sheet ?? new Document)->state()) {
                                        'published' => 'success', 'stale' => 'warning', 'unpublished' => 'info', default => 'gray',
                                    }),
                                TextEntry::make('sheet.generated_at')->label('Gerada em')->dateTime('d/m/Y H:i')->placeholder('—'),
                                TextEntry::make('sheet.public_url')->label('No site')->placeholder('ainda não enviada')
                                    ->url(fn (?string $state) => $state, shouldOpenInNewTab: true)
                                    ->limit(40),
                                TextEntry::make('chapter_sheets')->label('Capítulos')
                                    ->state(fn (Book $record) => Document::query()->where('documentable_type', 'chapter')
                                        ->whereIn('documentable_id', $record->chapters()->select('id'))->published()->where('stale', false)->count()
                                        .' de '.$record->chapters()->where('status', 'approved')->count().' ficha(s) publicada(s) e atualizada(s)'),
                            ]),

                        Section::make('Checklist')
                            ->schema([
                                Text::make(fn (Book $record) => new HtmlString(BookWizard::checklistHtml($record))),
                            ]),
                    ]),
            ]);
    }
}
