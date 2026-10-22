<?php

namespace App\Filament\Resources\Books\Tables;

use App\Filament\Resources\Books\BookResource;
use App\Models\Book;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BooksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('chapters')->with('contributions.person'))
            ->defaultSort('updated_at', 'desc')
            ->paginated([25, 50, 100])
            ->recordUrl(fn (Book $record) => BookResource::canEdit($record)
                ? BookResource::getUrl('edit', ['record' => $record])
                : BookResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('title')
                    ->label('Título')
                    ->weight(FontWeight::Medium)
                    ->description(fn (Book $record) => $record->contributorsLine() ?: $record->subtitle)
                    ->searchable(['title', 'subtitle', 'isbn_print', 'isbn_ebook', 'doi_suffix'])
                    ->sortable()
                    ->wrap(),

                TextColumn::make('doi')
                    ->label('DOI')
                    ->extraAttributes(['class' => 'hub-mono'])
                    ->copyable()
                    ->sortable(['doi_suffix']),

                TextColumn::make('publication_year')
                    ->label('Ano')
                    ->extraAttributes(['class' => 'hub-mono'])
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state) => Book::TYPES[$state] ?? $state)
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('chapters_count')
                    ->label('Capítulos')
                    ->badge()
                    ->color('gray')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Book::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => Book::statusColor($state))
                    ->sortable(),

                TextColumn::make('doi_status')
                    ->label('DOI')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Book::DOI_STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => Book::doiStatusColor($state))
                    ->sortable(),

                IconColumn::make('landing_url')
                    ->label('Site')
                    ->icon(fn (?string $state) => $state ? Heroicon::OutlinedArrowTopRightOnSquare : null)
                    ->color('primary')
                    ->url(fn (?string $state) => $state, shouldOpenInNewTab: true)
                    ->tooltip(fn (?string $state) => $state),

                TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Situação')->options(Book::STATUSES),
                SelectFilter::make('doi_status')->label('DOI')->options(Book::DOI_STATUSES),
                SelectFilter::make('type')->label('Tipo')->options(Book::TYPES),
                SelectFilter::make('publication_year')->label('Ano')
                    ->options(fn () => Book::query()->distinct()->orderByDesc('publication_year')->pluck('publication_year', 'publication_year')->all()),
            ])
            ->recordActions([
                ViewAction::make()->label('Resumo'),
                EditAction::make()->label('Editar'),
            ])
            ->emptyStateHeading('Nenhum livro cadastrado')
            ->emptyStateDescription('Use "Novo livro" para abrir o assistente de 8 etapas.');
    }
}
