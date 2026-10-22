<?php

namespace App\Console\Commands;

use App\Jobs\ProcessImport;
use App\Models\Import;
use App\Models\SyncLog;
use Illuminate\Console\Command;

/**
 * Importa um livro em PDF com IA, ponta a ponta (Conexão DOI, frente C).
 *
 *   php artisan hub:importar-pdf livro.pdf                  guarda o PDF e põe na fila
 *   php artisan hub:importar-pdf livro.pdf --sincrono       processa agora e mostra a proposta
 *   php artisan hub:importar-pdf livro.pdf --livro=12       liga a um livro já cadastrado
 *
 * Sem OPENAI_API_KEY só avisa. A proposta fica em imports.result; nada vai para
 * books/chapters sem a aprovação na tela de revisão.
 */
class ImportPdfCommand extends Command
{
    protected $signature = 'hub:importar-pdf {arquivo : Caminho do PDF}
        {--livro= : ID do livro já cadastrado a que a importação pertence}
        {--sincrono : Processa agora, sem fila, e mostra o resumo da proposta}';

    protected $description = 'Lê um livro em PDF com IA e monta a proposta de cadastro (livro, capítulos, pessoas)';

    public function handle(): int
    {
        if ((string) config('hub.ai.key') === '') {
            $this->warn('Serviço de IA não configurado (chave vazia no .env: OPENAI_API_KEY ou ANTHROPIC_API_KEY, conforme HUB_AI_PROVIDER); nada a fazer.');

            return self::SUCCESS;
        }

        $path = (string) $this->argument('arquivo');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error("Arquivo não encontrado ou sem leitura: {$path}");

            return self::FAILURE;
        }
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf' && ! str_starts_with((string) file_get_contents($path, false, null, 0, 5), '%PDF-')) {
            $this->error('O arquivo não parece um PDF.');

            return self::FAILURE;
        }

        $livro = $this->option('livro') !== null ? (int) $this->option('livro') : null;
        $import = Import::receive($path, basename($path), null, $livro);
        SyncLog::record(null, SyncLog::IN, 'import.received', $import,
            sprintf('%s (%s KB) recebido pela linha de comando', $import->original_name, number_format($import->size / 1024, 0, ',', '.')),
            ['file' => $import->file_path, 'book_id' => $livro]);

        $this->info("Importação #{$import->id} criada: {$import->original_name} → {$import->file_path}");

        if (! $this->option('sincrono')) {
            ProcessImport::dispatch($import->id);
            $this->line('Enviada à fila (queue:work database). Acompanhe em imports.status e na Auditoria (import.*).');

            return self::SUCCESS;
        }

        $this->line('Processando agora com '.config('hub.ai.model').'…');
        ProcessImport::dispatchSync($import->id);
        $import->refresh();

        if ($import->status === Import::ERROR) {
            $this->error("Falhou: {$import->error}");
            $this->line('Reprocessar: Import::find('.$import->id.')->reprocess() e ProcessImport::dispatch('.$import->id.').');

            return self::FAILURE;
        }

        $this->summary($import);

        return self::SUCCESS;
    }

    private function summary(Import $import): void
    {
        $r = $import->result ?? [];
        $book = $r['book'] ?? [];
        $usage = $import->usage ?? [];

        $this->newLine();
        $this->info(sprintf('Proposta #%d (%s) — %s', $import->id, $import->statusLabel(), $book['title'] ?? 'sem título'));
        $this->table(['Campo', 'Valor'], [
            ['Subtítulo', $book['subtitle'] ?? '—'],
            ['Tipo', $book['type'] ?? '—'],
            ['Editora / cidade / ano', implode(' / ', array_filter([$book['publisher'] ?? null, $book['publication_place'] ?? null, $book['publication_year'] ?? null])) ?: '—'],
            ['ISBN impresso / e-book', implode(' / ', array_filter([$book['isbn_print'] ?? null, $book['isbn_ebook'] ?? null])) ?: '—'],
            ['Pessoas da obra', count($book['contributors'] ?? [])],
            ['Capítulos', sprintf('%d (%d analisados, %d para revisão, %d falharam)', $r['summary']['chapters'] ?? 0, $r['summary']['analyzed'] ?? 0, $r['summary']['needs_review'] ?? 0, $r['summary']['failed'] ?? 0)],
            ['Resumos', sprintf('%d do PDF, %d sugeridos pela IA', $r['summary']['abstracts_from_pdf'] ?? 0, $r['summary']['abstracts_from_ai'] ?? 0)],
            ['Páginas', sprintf('%s no PDF, deslocamento impresso %s', $r['source']['pages'] ?? '?', $r['source']['page_offset'] ?? '?')],
            ['Campos da obra a revisar', implode(', ', $book['needs_review'] ?? []) ?: 'nenhum'],
            ['Chamadas / tokens', sprintf('%d chamadas, %s entrada, %s saída', $usage['calls'] ?? 0, number_format($usage['input_tokens'] ?? 0, 0, ',', '.'), number_format($usage['output_tokens'] ?? 0, 0, ',', '.'))],
            ['Custo estimado', 'US$ '.number_format($usage['cost_usd'] ?? 0, 2, ',', '.')],
        ]);

        $rows = [];
        foreach (array_slice($r['chapters'] ?? [], 0, 100) as $c) {
            $rows[] = [
                $c['sequence'],
                mb_substr($c['title'], 0, 50),
                ($c['first_page'] ?? '?').'–'.($c['last_page'] ?? '?'),
                implode('; ', array_map(fn ($a) => $a['name'], array_slice($c['contributors'] ?? [], 0, 3))).(count($c['contributors'] ?? []) > 3 ? '…' : ''),
                $c['abstract_source'] ?? '—',
                implode(',', $c['needs_review'] ?? []) ?: 'ok',
            ];
        }
        if ($rows) {
            $this->table(['#', 'Título', 'Págs.', 'Autores', 'Resumo', 'Revisar'], $rows);
        }
        foreach ($r['warnings'] ?? [] as $w) {
            $this->warn($w);
        }
    }
}
