<?php

namespace App\Filament\Resources\Chapters\Tables;

use App\Filament\Resources\Books\DocumentActions;
use App\Models\Book;
use App\Models\Chapter;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Colunas e filtros dos capítulos, iguais na lista geral e dentro do livro. */
class ChaptersTable
{
    public static function configure(Table $table, bool $withBook): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['contributions.person', 'book', 'sheet']))
            ->defaultSort('sequence')
            ->paginated([25, 50, 100])
            ->columns(array_values(array_filter([
                $withBook ? TextColumn::make('book.title')->label('Livro')->limit(40)->tooltip(fn (Chapter $record) => $record->book?->title)->sortable()->searchable() : null,

                TextColumn::make('sequence')->label('#')->extraAttributes(['class' => 'hub-mono'])->sortable(),

                TextColumn::make('title')
                    ->label('Capítulo')
                    ->weight(FontWeight::Medium)
                    ->description(fn (Chapter $record) => trim(($record->number ? "Cap. {$record->number}" : '').($record->section ? ' · '.$record->section : ''), ' ·') ?: null)
                    ->searchable(['title', 'subtitle'])
                    ->wrap(),

                TextColumn::make('authors')
                    ->label('Autores')
                    ->state(fn (Chapter $record) => $record->authorsLine() ?: null)
                    ->placeholder('—')
                    ->limit(60)
                    ->tooltip(fn (Chapter $record) => $record->authorsLine())
                    ->wrap(),

                TextColumn::make('pages')
                    ->label('Páginas')
                    ->state(fn (Chapter $record) => $record->pagesLabel())
                    ->extraAttributes(['class' => 'hub-mono'])
                    ->alignEnd(),

                TextColumn::make('doi')
                    ->label('DOI proposto')
                    ->extraAttributes(['class' => 'hub-mono'])
                    ->copyable(),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Chapter::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => Chapter::statusColor($state))
                    ->sortable(),

                TextColumn::make('doi_status')
                    ->label('DOI')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Book::DOI_STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => Book::doiStatusColor($state))
                    ->sortable(),

                DocumentActions::column(),
            ])))
            ->filters(array_values(array_filter([
                $withBook ? SelectFilter::make('book_id')->label('Livro')->relationship('book', 'title')->searchable()->preload() : null,
                SelectFilter::make('status')->label('Situação')->options(Chapter::STATUSES),
                SelectFilter::make('doi_status')->label('DOI')->options(Book::DOI_STATUSES),
            ])));
    }
}
