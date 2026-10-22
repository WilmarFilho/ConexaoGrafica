<?php

namespace App\Filament\Resources\Imports\Tables;

use App\Filament\Resources\Books\BookResource;
use App\Filament\Resources\Imports\ImportResource;
use App\Jobs\ProcessImport;
use App\Models\Import;
use App\Models\SyncLog;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class ImportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['book', 'creator']))
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->paginated([25, 50])
            ->recordUrl(fn (Import $record) => ImportResource::getUrl('review', ['record' => $record]))
            ->columns([
                TextColumn::make('original_name')
                    ->label('Arquivo')
                    ->weight(FontWeight::Medium)
                    ->description(fn (Import $record) => $record->result['book']['title'] ?? null)
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('book.title')
                    ->label('Livro')
                    ->placeholder('—')
                    ->url(fn (Import $record) => $record->book_id ? BookResource::getUrl('edit', ['record' => $record->book_id]) : null)
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Import::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => Import::statusColor($state))
                    ->description(fn (Import $record) => $record->status === Import::ERROR ? mb_substr((string) $record->error, 0, 80) : null)
                    ->sortable(),

                TextColumn::make('chapters')
                    ->label('Capítulos')
                    ->state(fn (Import $record) => $record->result['summary']['chapters'] ?? null)
                    ->badge()->color('gray')->alignEnd()
                    ->placeholder('—'),

                TextColumn::make('analyzed')
                    ->label('Analisados')
                    ->state(fn (Import $record) => $record->result['summary']['analyzed'] ?? null)
                    ->badge()->color('info')->alignEnd()
                    ->placeholder('—'),

                TextColumn::make('needs_review')
                    ->label('A revisar')
                    ->state(fn (Import $record) => $record->result['summary']['needs_review'] ?? null)
                    ->badge()
                    ->color(fn ($state) => ((int) $state) > 0 ? 'warning' : 'success')
                    ->alignEnd()
                    ->placeholder('—'),

                TextColumn::make('approved')
                    ->label('Aprovados')
                    ->state(fn (Import $record) => $record->hasProposal() ? $record->approvedChaptersCount().'/'.$record->chaptersCount() : null)
                    ->extraAttributes(['class' => 'hub-mono'])
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('cost')
                    ->label('Custo')
                    ->state(fn (Import $record) => $record->cost())
                    ->money('USD')
                    ->extraAttributes(['class' => 'hub-mono'])
                    ->placeholder('—')
                    ->alignEnd(),

                TextColumn::make('creator.name')
                    ->label('Enviado por')
                    ->placeholder('linha de comando')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Enviado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Situação')->options(Import::STATUSES),
            ])
            ->recordActions([
                Action::make('revisar')
                    ->label(fn (Import $record) => $record->hasProposal() ? ($record->status === Import::INTEGRATED ? 'Ver' : 'Revisar') : 'Acompanhar')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (Import $record) => ImportResource::getUrl('review', ['record' => $record])),

                Action::make('reprocessar')
                    ->label('Reprocessar')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->visible(fn (Import $record) => $record->canReprocess())
                    ->requiresConfirmation()
                    ->modalHeading('Reprocessar a importação?')
                    ->modalDescription('A proposta atual (e o que já foi revisado nela) é descartada; o PDF continua no disco e a IA é chamada de novo.')
                    ->action(function (Import $record) {
                        $record->reprocess();
                        SyncLog::record(null, SyncLog::MANUAL, 'import.reprocess', $record, "Importação #{$record->id} reenviada à fila");
                        ProcessImport::dispatch($record->id);
                        Notification::make()->title('Importação reenviada à fila')->success()->send();
                    }),

                DeleteAction::make()
                    ->label('Excluir')
                    ->modalDescription('O PDF é apagado do disco junto com a proposta.')
                    ->after(function (Import $record) {
                        Storage::disk('local')->delete($record->file_path);
                    }),
            ])
            ->emptyStateHeading('Nenhuma importação ainda')
            ->emptyStateDescription('Envie o PDF de um livro: a IA monta a proposta de cadastro e você revisa lado a lado com o arquivo.');
    }
}
