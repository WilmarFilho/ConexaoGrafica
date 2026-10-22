<?php

namespace App\Filament\Resources\Imports\Pages;

use App\Editorial\EditorialException;
use App\Editorial\Import\Integrator;
use App\Filament\Resources\Books\BookResource;
use App\Filament\Resources\Imports\ImportResource;
use App\Filament\Resources\Imports\Schemas\ReviewForm;
use App\Jobs\ProcessImport;
use App\Models\Import;
use App\Models\SyncLog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Revisão lado a lado da proposta da IA (Conexão DOI, frente E2): à esquerda o
 * PDF (rota autenticada, iframe com #page=N), à direita o formulário por abas
 * sobre `imports.result`. O formulário carrega `book` e o capítulo atual;
 * navegar, aprovar e salvar gravam de volta no `result` (nada se perde).
 *
 * Estados: abrir uma proposta `suggested` leva a `review`; aprovada a obra e
 * todos os capítulos → `approved`; "Integrar ao catálogo" (Integrator) → `integrated`.
 */
class ReviewImport extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ImportResource::class;

    protected string $view = 'filament.resources.imports.review';

    protected static ?string $breadcrumb = 'Revisão';

    /** @var array<string, mixed> `book` + `chapter` (o atual) */
    public ?array $data = [];

    /** Índice do capítulo em edição (em `result.chapters`). */
    public int $current = 0;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(ImportResource::canAccess(), 403);

        $import = $this->getRecord();
        if ($import->status === Import::SUGGESTED) {
            $import->forceFill(['status' => Import::REVIEW, 'reviewed_by' => auth()->id()])->save();
            SyncLog::record(null, SyncLog::MANUAL, 'import.review', $import, "Revisão da importação #{$import->id} iniciada");
        }

        $this->fillForm();
    }

    public function getTitle(): string
    {
        $import = $this->getRecord();

        return ($import->result['book']['title'] ?? null) ?: $import->original_name;
    }

    public function getSubheading(): ?string
    {
        $import = $this->getRecord();
        $bits = [$import->statusLabel()];
        if ($import->hasProposal()) {
            $bits[] = $import->approvalSummary();
        }
        if ($import->cost() !== null) {
            $bits[] = 'custo estimado US$ '.number_format($import->cost(), 2, ',', '.');
        }
        $bits[] = $import->original_name.' · '.number_format($import->size / 1024 / 1024, 1, ',', '.').' MB'.($import->pages ? " · {$import->pages} páginas" : '');

        return implode(' · ', $bits);
    }

    // ---------------------------------------------------------------- formulário

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(ReviewForm::components($this))
            ->statePath('data')
            ->disabled(fn () => ! $this->getRecord()->isEditable());
    }

    protected function fillForm(): void
    {
        $r = $this->getRecord()->result ?? [];
        $this->current = max(0, min($this->current, max(0, count($r['chapters'] ?? []) - 1)));

        $this->form->fill([
            'book' => $this->withIndexes($r['book'] ?? [], ['contributors']),
            'chapter' => $this->chapterData($r, $this->current),
        ]);
    }

    private function chapterData(array $r, int $i): array
    {
        $c = $r['chapters'][$i] ?? [];
        if ($c === []) {
            return [];
        }
        if (blank($c['abstract_approved'] ?? null)) {
            $c['abstract_approved'] = $c['abstract_original'] ?? $c['abstract_ai'] ?? null;
        }

        return $this->withIndexes($c, ['contributors']);
    }

    /** Marca cada item das listas com `_idx` (posição original) para a gravação casar o que não está no formulário. */
    private function withIndexes(array $data, array $lists): array
    {
        foreach ($lists as $list) {
            $data[$list] = array_values(array_map(fn ($item, $i) => (is_array($item) ? $item : []) + ['_idx' => $i], (array) ($data[$list] ?? []), array_keys((array) ($data[$list] ?? []))));
        }

        return $data;
    }

    /** Grava o formulário de volta em `result` (obra + capítulo atual). */
    protected function persist(): Import
    {
        $import = $this->getRecord();
        if (! $import->isEditable()) {
            return $import;
        }

        $state = $this->form->getState();
        $r = $import->result;

        $r['book'] = self::merge($r['book'] ?? [], $state['book'] ?? [], ['contributors']);
        if (isset($r['chapters'][$this->current]) && isset($state['chapter'])) {
            $r['chapters'][$this->current] = self::merge($r['chapters'][$this->current], $state['chapter'], ['contributors']);
        }

        $import->forceFill(['result' => $r])->save();

        return $import;
    }

    /** Campos do formulário sobre o original; listas casadas por `_idx` (evidência, confiança e pendências ficam). */
    private static function merge(array $original, array $state, array $lists): array
    {
        foreach ($state as $key => $value) {
            if (in_array($key, $lists, true)) {
                $items = [];
                foreach (array_values((array) $value) as $n => $item) {
                    $item = is_array($item) ? $item : [];
                    $idx = $item['_idx'] ?? null;
                    unset($item['_idx']);
                    if (array_key_exists('book_contributor_index', $item)) {
                        $item['book_contributor_index'] = $item['book_contributor_index'] === null || $item['book_contributor_index'] === '' ? null : (int) $item['book_contributor_index'];
                    }
                    $base = $idx !== null && isset($original[$key][$idx]) && is_array($original[$key][$idx]) ? $original[$key][$idx] : [];
                    $merged = array_merge($base, $item);
                    if (array_key_exists('sequence', $base) || isset($item['name'])) {
                        $merged['sequence'] = $n + 1;
                    }
                    $items[] = $merged;
                }
                $original[$key] = $items;
            } elseif (is_array($value) && isset($original[$key]) && is_array($original[$key]) && ! array_is_list($value) && ! array_is_list($original[$key])) {
                $original[$key] = array_merge($original[$key], $value); // ex.: series {title, issn}
            } else {
                $original[$key] = $value;
            }
        }

        return $original;
    }

    // ------------------------------------------------------------------ navegação

    public function goTo(int $index): void
    {
        $n = $this->getRecord()->chaptersCount();
        if ($index < 0 || $index >= $n) {
            return;
        }

        $this->persist();
        $this->current = $index;
        $this->fillForm();
    }

    public function save(): void
    {
        $this->persist();
        Notification::make()->title('Revisão salva')->success()->send();
    }

    // ------------------------------------------------------------------- dados

    /** `result.book` como está gravado (evidência, pendências, aprovação). */
    public function bookProposal(): array
    {
        return (array) ($this->getRecord()->result['book'] ?? []);
    }

    public function currentChapter(): array
    {
        return (array) ($this->getRecord()->result['chapters'][$this->current] ?? []);
    }

    /** Lista lateral: um resumo por capítulo. */
    public function chaptersOutline(): array
    {
        $out = [];
        foreach (array_values((array) ($this->getRecord()->result['chapters'] ?? [])) as $i => $c) {
            $out[] = [
                'index' => $i,
                'sequence' => $c['sequence'] ?? $i + 1,
                'number' => $c['number'] ?? null,
                'title' => $c['title'] ?? '(sem título)',
                'section' => $c['section'] ?? null,
                'pages' => filled($c['first_page'] ?? null) ? ($c['first_page'].'–'.($c['last_page'] ?? '?')) : null,
                'pdf_page' => $c['pdf_first_page'] ?? null,
                'status' => $c['status'] ?? 'pending',
                'approved' => ! empty($c['approved']),
                'needs' => count($c['needs_review'] ?? []),
                'ai_abstract' => ($c['abstract_source'] ?? null) === 'ai',
            ];
        }

        return $out;
    }

    public function pdfUrl(): string
    {
        return route('hub.imports.pdf', $this->getRecord());
    }

    public function initialPdfPage(): int
    {
        return (int) ($this->currentChapter()['pdf_first_page'] ?? 1) ?: 1;
    }

    // ------------------------------------------------------------------- ações

    protected function getHeaderActions(): array
    {
        $editable = fn () => $this->getRecord()->isEditable();

        return [
            Action::make('salvar')
                ->label('Salvar')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('gray')
                ->visible($editable)
                ->action(fn () => $this->save()),

            Action::make('aprovar_obra')
                ->label(fn () => $this->getRecord()->bookApproved() ? 'Reabrir obra' : 'Aprovar obra')
                ->icon(fn () => $this->getRecord()->bookApproved() ? Heroicon::OutlinedArrowUturnLeft : Heroicon::OutlinedCheckBadge)
                ->color(fn () => $this->getRecord()->bookApproved() ? 'gray' : 'success')
                ->visible($editable)
                ->action(fn () => $this->toggleBook()),

            Action::make('aprovar_capitulo')
                ->label(fn () => ($this->getRecord()->chapterApproved($this->current) ? 'Reabrir capítulo ' : 'Aprovar capítulo ').($this->currentChapter()['sequence'] ?? $this->current + 1))
                ->icon(fn () => $this->getRecord()->chapterApproved($this->current) ? Heroicon::OutlinedArrowUturnLeft : Heroicon::OutlinedCheckBadge)
                ->color(fn () => $this->getRecord()->chapterApproved($this->current) ? 'gray' : 'success')
                ->visible(fn () => $editable() && $this->getRecord()->chaptersCount() > 0)
                ->action(fn () => $this->toggleChapter($this->current)),

            Action::make('aprovar_todos')
                ->label('Aprovar todos sem pendência')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn () => $editable() && $this->getRecord()->chaptersCount() > 0)
                ->requiresConfirmation()
                ->modalHeading('Aprovar todos os capítulos sem pendência?')
                ->modalDescription('Só entram os capítulos analisados pela IA e sem nenhum item em "revisar". Os outros continuam para revisão manual.')
                ->action(fn () => $this->approveAllClean()),

            Action::make('integrar')
                ->label('Integrar ao catálogo')
                ->icon(Heroicon::OutlinedArrowDownOnSquare)
                ->color('primary')
                ->visible($editable)
                ->requiresConfirmation()
                ->modalHeading('Integrar a proposta ao catálogo?')
                ->modalDescription(fn () => ($this->getRecord()->book_id ? 'O livro vinculado é atualizado' : 'Um livro novo é criado em rascunho')
                    .', com capítulos, pessoas e instituições. Pendências (ORCID/instituição, resumo vazio) seguem para o checklist do livro.')
                ->action(fn () => $this->integrate()),

            Action::make('reprocessar')
                ->label('Reprocessar')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn () => $this->getRecord()->canReprocess())
                ->requiresConfirmation()
                ->modalHeading('Reprocessar a importação?')
                ->modalDescription('A proposta atual e tudo o que foi revisado nela são descartados; o PDF continua no disco e a IA é chamada de novo.')
                ->action(fn () => $this->reprocessImport()),

            Action::make('abrir_livro')
                ->label('Abrir livro')
                ->icon(Heroicon::OutlinedBookmarkSquare)
                ->color('primary')
                ->visible(fn () => $this->getRecord()->status === Import::INTEGRATED && $this->getRecord()->book_id)
                ->url(fn () => BookResource::getUrl('edit', ['record' => $this->getRecord()->book_id])),

            Action::make('lista')
                ->label('Importações')
                ->icon(Heroicon::OutlinedQueueList)
                ->color('gray')
                ->url(ImportResource::getUrl()),
        ];
    }

    public function toggleBook(): void
    {
        $import = $this->persist();
        $r = $import->result;
        $approving = empty($r['book']['approved']);

        if ($approving) {
            $missing = array_filter([
                blank($r['book']['title'] ?? null) ? 'título' : null,
                (int) ($r['book']['publication_year'] ?? 0) < 1900 ? 'ano de publicação' : null,
            ]);
            if ($missing) {
                Notification::make()->title('Não foi possível')->body('Preencha na aba Obra: '.implode(', ', $missing).'.')->danger()->persistent()->send();

                return;
            }
        }

        $r['book']['approved'] = $approving;
        $import->forceFill(['result' => $r])->save();
        $this->syncStatus($import);

        Notification::make()->title($approving ? 'Obra aprovada' : 'Obra reaberta para revisão')->success()->send();
    }

    public function toggleChapter(int $index): void
    {
        $import = $this->persist();
        $r = $import->result;
        if (! isset($r['chapters'][$index])) {
            return;
        }
        $approving = empty($r['chapters'][$index]['approved']);

        if ($approving && blank($r['chapters'][$index]['title'] ?? null)) {
            Notification::make()->title('Não foi possível')->body('O capítulo precisa de título.')->danger()->persistent()->send();

            return;
        }

        $r['chapters'][$index]['approved'] = $approving;
        $import->forceFill(['result' => $r])->save();
        $this->syncStatus($import);

        $seq = $r['chapters'][$index]['sequence'] ?? $index + 1;
        Notification::make()->title($approving ? "Capítulo {$seq} aprovado" : "Capítulo {$seq} reaberto")->success()->send();
    }

    public function approveAllClean(): void
    {
        $import = $this->persist();
        $r = $import->result;
        $ok = 0;
        $left = 0;

        foreach ($r['chapters'] ?? [] as $i => $c) {
            if (! empty($c['approved'])) {
                continue;
            }
            if (($c['status'] ?? '') === 'suggested' && empty($c['needs_review']) && filled($c['title'] ?? null)) {
                $r['chapters'][$i]['approved'] = true;
                $ok++;
            } else {
                $left++;
            }
        }

        $import->forceFill(['result' => $r])->save();
        $this->syncStatus($import);

        Notification::make()
            ->title("{$ok} capítulo(s) aprovado(s)")
            ->body($left ? "{$left} com pendência ficaram para revisão manual." : null)
            ->{$left ? 'warning' : 'success'}()
            ->send();
    }

    /** `approved` quando obra + todos os capítulos estão aprovados; volta a `review` se algo for reaberto. */
    private function syncStatus(Import $import): void
    {
        if ($import->isFullyApproved()) {
            if ($import->status !== Import::APPROVED) {
                $import->forceFill(['status' => Import::APPROVED, 'approved_at' => now(), 'reviewed_by' => auth()->id() ?? $import->reviewed_by])->save();
                SyncLog::record(null, SyncLog::MANUAL, 'import.approved', $import,
                    "Proposta da importação #{$import->id} aprovada: ".$import->approvalSummary());
            }
        } elseif ($import->status === Import::APPROVED) {
            $import->forceFill(['status' => Import::REVIEW, 'approved_at' => null])->save();
        }
    }

    public function integrate(): void
    {
        $import = $this->persist();

        if (! $import->isFullyApproved() || $import->status !== Import::APPROVED) {
            Notification::make()->title('Não foi possível')
                ->body('Integre só depois de aprovar a obra e todos os capítulos: '.$import->approvalSummary().'.')
                ->danger()->persistent()->send();

            return;
        }

        try {
            $book = Integrator::integrate($import);
        } catch (EditorialException $e) {
            Notification::make()->title('Não foi possível')->body($e->getMessage())->danger()->persistent()->send();

            return;
        }

        Notification::make()
            ->title('Proposta integrada ao catálogo')
            ->body('Livro "'.$book->title.'" com '.$book->chapters()->count().' capítulo(s). Confira o checklist e siga o fluxo editorial.')
            ->success()->persistent()->send();

        $this->redirect(BookResource::getUrl('edit', ['record' => $book]));
    }

    public function reprocessImport(): void
    {
        $import = $this->getRecord();
        if (! $import->canReprocess()) {
            return;
        }

        $import->reprocess();
        SyncLog::record(null, SyncLog::MANUAL, 'import.reprocess', $import, "Importação #{$import->id} reenviada à fila");
        ProcessImport::dispatch($import->id);

        Notification::make()->title('Importação reenviada à fila')->success()->send();
        $this->redirect(ImportResource::getUrl());
    }
}
