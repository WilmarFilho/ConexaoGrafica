<?php

namespace App\Filament\Resources\Books;

use App\Crossref\BookDeposits;
use App\Crossref\CrossrefException;
use App\Crossref\DepositService;
use App\Editorial\EditorialException;
use App\Models\Book;
use App\Models\Deposit;
use App\Models\DepositItem;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Ações de depósito na Crossref (frente E1), no cabeçalho da tela de resumo do
 * livro, só para os perfis Depósito e Administrador. Quem decide é o
 * BookDeposits; aqui se mostra o botão certo, a confirmação com o ambiente e os
 * DOIs do lote, e se traduz o erro em aviso.
 */
class DepositActions
{
    /** @return array<Action> */
    public static function all(): array
    {
        $canDeposit = fn () => (bool) auth()->user()?->canDeposit();

        return [
            Action::make('registrar_doi')
                ->label(fn (Book $record) => $record->doi_status === 'registered' ? 'Atualizar DOI na Crossref' : 'Registrar DOI')
                ->icon(Heroicon::OutlinedCloudArrowUp)
                ->color(fn () => self::isProduction() ? 'danger' : 'primary')
                ->visible(fn (Book $record) => $canDeposit() && $record->isPublished())
                ->requiresConfirmation()
                ->modalIcon(Heroicon::OutlinedCloudArrowUp)
                ->modalHeading(fn (Book $record) => ($record->doi_status === 'registered' ? 'Atualizar o registro na Crossref?' : 'Registrar DOI na Crossref?'))
                ->modalDescription(fn (Book $record) => new HtmlString(self::confirmationHtml($record)))
                ->modalWidth('2xl')
                ->modalSubmitActionLabel(fn () => self::isProduction() ? 'Registrar em produção' : 'Registrar em homologação')
                ->action(function (Book $record) {
                    $record->refresh();

                    try {
                        $deposit = BookDeposits::make()->register($record, auth()->user());

                        Notification::make()
                            ->title("Depósito {$deposit->batch_id} enfileirado")
                            ->body(sprintf('%d DOI(s) para %s. O envio sai pela fila; o registro de cada DOI vem do relatório da Crossref (consulte o resultado ou aguarde a varredura).',
                                $deposit->items->count(), $deposit->environment_label))
                            ->success()->send();
                    } catch (EditorialException $e) {
                        Notification::make()->title('Não foi possível')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),

            Action::make('consultar_resultado')
                ->label('Consultar resultado')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (Book $record) => $canDeposit() && (bool) BookDeposits::latestDeposit($record)?->awaitingResult())
                ->action(function (Book $record) {
                    $deposit = BookDeposits::latestDeposit($record);
                    if (! $deposit) {
                        return;
                    }

                    try {
                        $service = DepositService::make();
                        $deposit = $service->refresh($deposit);
                        BookDeposits::make($service)->sync($deposit);

                        Notification::make()
                            ->title("Depósito {$deposit->batch_id}: {$deposit->status_label}")
                            ->body(self::itemsSummary($deposit))
                            ->status(match ($deposit->status) {
                                Deposit::FAILED => 'danger',
                                Deposit::DONE => 'success',
                                default => 'info',
                            })
                            ->send();
                    } catch (ConnectionException|CrossrefException $e) {
                        Notification::make()->title('Não foi possível consultar')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),

            Action::make('baixar_xml')
                ->label('Baixar XML')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (Book $record) => $canDeposit() && BookDeposits::latestDeposit($record) !== null)
                ->action(function (Book $record) {
                    $deposit = BookDeposits::latestDeposit($record);

                    return self::download($deposit);
                }),
        ];
    }

    /** Resposta de download do XML gravado, ou aviso se o arquivo sumiu do storage. */
    public static function download(?Deposit $deposit): mixed
    {
        if (! $deposit || ! Storage::disk('local')->exists($deposit->xml_path)) {
            Notification::make()->title('XML não encontrado')
                ->body($deposit ? "O arquivo {$deposit->xml_path} não está mais no storage; gere um depósito novo." : 'Este livro ainda não tem depósito.')
                ->danger()->send();

            return null;
        }

        return Storage::disk('local')->download($deposit->xml_path, $deposit->filename());
    }

    public static function isProduction(): bool
    {
        return (config('hub.crossref.env') ?: 'test') === 'production';
    }

    /** Corpo da confirmação: ambiente, aviso de cobrança e os DOIs que vão no lote. */
    public static function confirmationHtml(Book $book): string
    {
        $dois = BookDeposits::dois($book);
        $n = count($dois);
        $prod = self::isProduction();

        $html = '<div style="display:grid;gap:10px;font-size:.9rem;text-align:left">';

        if ($prod) {
            $html .= '<div style="padding:8px 10px;border-radius:6px;background:var(--hub-danger-bg);color:var(--hub-danger);border:1px solid var(--hub-danger-bd)">'
                .'<strong>Ambiente: produção</strong> (doi.crossref.org). Os DOIs passam a existir de verdade e '
                .'<strong>a Crossref cobra por DOI registrado</strong>: '.$n.' DOI(s) neste lote. Atualização do mesmo DOI não gera nova cobrança.</div>';
        } else {
            $html .= '<div style="padding:8px 10px;border-radius:6px;background:var(--hub-info-bg);color:var(--hub-info);border:1px solid var(--hub-info-bd)">'
                .'<strong>Ambiente: homologação</strong> (test.crossref.org). Nada é registrado de verdade nem cobrado; em produção a Crossref cobra por DOI.</div>';
        }

        if ($book->doi_status === 'registered') {
            $html .= '<div style="color:var(--hub-muted)">O livro já está registrado: este envio atualiza os metadados e as URLs dos mesmos DOIs (o sufixo não muda).</div>';
        }

        if ($why = BookDeposits::blocker($book)) {
            $html .= '<div style="color:var(--hub-warning)"><strong>⚠</strong> '.e($why).'</div>';
        }

        $html .= '<div><strong>'.$n.' DOI(s) no lote</strong></div>'
            .'<ul class="hub-mono" style="display:grid;gap:4px;font-size:.8rem;max-height:16rem;overflow:auto;padding-right:4px">';
        foreach ($dois as $d) {
            $html .= '<li>'.e($d['doi']).' <span style="color:var(--hub-muted);font-family:inherit">— '.e($d['label']).'</span></li>';
        }

        return $html.'</ul></div>';
    }

    /** "1 registrado · 1 com falha · 1 aguardando" */
    public static function itemsSummary(Deposit $deposit): string
    {
        $items = $deposit->items;
        $registered = $items->where('status', DepositItem::REGISTERED)->count();
        $failed = $items->where('status', DepositItem::FAILED)->count();
        $pending = $items->where('status', DepositItem::PENDING)->count();

        return "{$registered} registrado(s) · {$failed} com falha · {$pending} aguardando";
    }

    /** Tabela dos itens de um depósito (DOI, estado, mensagem da Crossref). */
    public static function itemsHtml(Deposit $deposit): string
    {
        $deposit->loadMissing('items');

        $html = '<div style="font-size:.85rem;text-align:left"><div style="color:var(--hub-muted);margin-bottom:8px">'
            .'Lote <span class="hub-mono">'.e($deposit->batch_id).'</span> · '.e($deposit->environment_label).' · '.e($deposit->status_label)
            .($deposit->submitted_at ? ' · enviado em '.$deposit->submitted_at->format('d/m/Y H:i') : '')
            .($deposit->result_at ? ' · resultado em '.$deposit->result_at->format('d/m/Y H:i') : '')
            .'</div><table style="width:100%;border-collapse:collapse"><thead><tr style="color:var(--hub-muted);font-size:.78rem;text-align:left">'
            .'<th style="padding:4px 6px">DOI</th><th style="padding:4px 6px">Alvo</th><th style="padding:4px 6px">Estado</th><th style="padding:4px 6px">Mensagem</th></tr></thead><tbody>';

        foreach ($deposit->items as $item) {
            $color = match ($item->status) {
                DepositItem::REGISTERED => 'var(--hub-success)',
                DepositItem::FAILED => 'var(--hub-danger)',
                default => 'var(--hub-warning)',
            };
            $html .= '<tr style="border-top:1px solid var(--hub-border)">'
                .'<td class="hub-mono" style="padding:4px 6px;white-space:nowrap">'.e($item->doi).'</td>'
                .'<td style="padding:4px 6px">'.($item->target_type === DepositItem::BOOK ? 'Livro' : 'Capítulo').' #'.(int) $item->target_id.'</td>'
                .'<td style="padding:4px 6px;color:'.$color.';font-weight:600">'.e($item->status_label).'</td>'
                .'<td style="padding:4px 6px;color:var(--hub-muted)">'.e((string) $item->message ?: '—').'</td></tr>';
        }

        return $html.'</tbody></table></div>';
    }
}
