<?php

namespace App\Filament\Resources\Books\RelationManagers;

use App\Crossref\BookDeposits;
use App\Crossref\CrossrefException;
use App\Crossref\DepositService;
use App\Filament\Resources\Books\DepositActions;
use App\Filament\Resources\Books\Pages\ViewBook;
use App\Models\Deposit;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\HtmlString;

/**
 * Histórico de depósitos do livro na Crossref (frente E1): lote, ambiente,
 * situação, itens com DOI/estado/mensagem e datas. Somente leitura; aparece na
 * tela de resumo para os perfis Depósito e Administrador.
 */
class DepositsRelationManager extends RelationManager
{
    protected static string $relationship = 'deposits';

    protected static ?string $title = 'Depósitos na Crossref';

    protected static ?string $modelLabel = 'depósito';

    protected static ?string $pluralModelLabel = 'depósitos';

    /** Tabela pequena: vem junto com a página, sem carregamento tardio. */
    protected static bool $isLazy = false;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $pageClass === ViewBook::class && (bool) auth()->user()?->canDeposit();
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['items', 'creator']))
            ->defaultSort('id', 'desc')
            ->paginated([10, 25])
            ->columns([
                TextColumn::make('batch_id')
                    ->label('Lote')
                    ->extraAttributes(['class' => 'hub-mono'])
                    ->copyable()
                    ->description(fn (Deposit $record) => $record->creator?->name),

                TextColumn::make('environment')
                    ->label('Ambiente')
                    ->badge()
                    ->formatStateUsing(fn (Deposit $record) => $record->environment_label)
                    ->color(fn (string $state) => $state === Deposit::PRODUCTION ? 'danger' : 'gray'),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->formatStateUsing(fn (Deposit $record) => $record->status_label)
                    ->color(fn (string $state) => Deposit::statusColor($state)),

                TextColumn::make('items_summary')
                    ->label('DOIs')
                    ->state(fn (Deposit $record) => DepositActions::itemsSummary($record))
                    ->description(fn (Deposit $record) => $record->http_status ? "HTTP {$record->http_status}" : null),

                TextColumn::make('submitted_at')
                    ->label('Enviado em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),

                TextColumn::make('result_at')
                    ->label('Resultado em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Gerado em')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('itens')
                    ->label('Itens')
                    ->icon(Heroicon::OutlinedListBullet)
                    ->color('gray')
                    ->modalHeading(fn (Deposit $record) => 'DOIs do depósito '.$record->batch_id)
                    ->modalContent(fn (Deposit $record) => new HtmlString(DepositActions::itemsHtml($record)))
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),

                Action::make('consultar')
                    ->label('Consultar')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->visible(fn (Deposit $record) => $record->awaitingResult())
                    ->action(function (Deposit $record) {
                        try {
                            $service = DepositService::make();
                            $record = $service->refresh($record);
                            BookDeposits::make($service)->sync($record);

                            Notification::make()->title("Depósito {$record->batch_id}: {$record->status_label}")
                                ->body(DepositActions::itemsSummary($record))->info()->send();
                        } catch (ConnectionException|CrossrefException $e) {
                            Notification::make()->title('Não foi possível consultar')->body($e->getMessage())->danger()->persistent()->send();
                        }
                    }),

                Action::make('baixar_xml')
                    ->label('XML')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->action(fn (Deposit $record) => DepositActions::download($record)),
            ])
            ->emptyStateHeading('Nenhum depósito ainda')
            ->emptyStateDescription('Use "Registrar DOI" no cabeçalho quando o livro estiver publicado no site.');
    }
}
