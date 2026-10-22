<?php

namespace App\Jobs;

use App\Editorial\Import\Ai\AiException;
use App\Editorial\Import\Analyzer;
use App\Editorial\Import\PdfText;
use App\Models\Import;
use App\Models\SyncLog;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Processa uma importação de PDF (Conexão DOI, frente C) pela fila `database`:
 *
 *   received → extracting (texto por página + dados da obra via IA)
 *            → segmenting (capítulos ↔ páginas; um capítulo por chamada à IA)
 *            → suggested (proposta completa em imports.result)
 *            → error (mensagem em imports.error; Import::reprocess() volta a received)
 *
 * A proposta nunca grava em books/chapters: isso é a aprovação, na tela de revisão.
 * Cada etapa registra em SyncLog (import.*). O result é salvo a cada capítulo, então
 * a revisão pode acompanhar o progresso e um erro no meio não perde o que já saiu.
 */
class ProcessImport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Uma tentativa só: falha fica registrada no Import e o reprocessamento é manual. */
    public int $tries = 1;

    /** Livro de 500 páginas e 74 capítulos leva dezenas de minutos. */
    public int $timeout = 3 * 3600;

    /** Fila própria: um livro grande leva horas e não pode segurar os pedidos da loja. */
    public const QUEUE = 'longos';

    public function __construct(public int $importId)
    {
        $this->onQueue(self::QUEUE);
    }

    public function uniqueId(): string
    {
        return (string) $this->importId;
    }

    public function handle(Analyzer $analyzer, PdfText $pdfText): void
    {
        $import = Import::find($this->importId);
        if (! $import || $import->status !== Import::RECEIVED) {
            return;
        }

        try {
            $this->run($import, $analyzer, $pdfText);
        } catch (Throwable $e) {
            $msg = mb_substr($e->getMessage(), 0, 1000);
            $import->forceFill([
                'status' => Import::ERROR,
                'error' => $msg,
                'usage' => $analyzer->usage->toArray(),
            ])->save();
            SyncLog::record(null, SyncLog::IN, 'import.failed', $import, "Importação #{$import->id} falhou: {$msg}",
                ['file' => $import->original_name, 'reason' => $e instanceof AiException ? $e->reason : get_class($e)], 'error');
        }
    }

    private function run(Import $import, Analyzer $analyzer, PdfText $pdfText): void
    {
        $model = (string) config('hub.ai.model');
        if ((string) config('hub.ai.key') === '') {
            throw new \RuntimeException('OPENAI_API_KEY não configurada; a importação com IA está desligada.');
        }

        // 1. Extração ---------------------------------------------------------
        $import->forceFill(['status' => Import::EXTRACTING, 'error' => null, 'model' => $model, 'prompt_version' => Analyzer::PROMPT_VERSION])->save();

        $pages = $pdfText->extract($import->absolutePath());
        $withText = PdfText::pagesWithText($pages);
        $import->forceFill(['pages' => count($pages)])->save();
        SyncLog::record(null, SyncLog::IN, 'import.extracted', $import,
            sprintf('%d página(s), %d com texto', count($pages), $withText), ['pages' => count($pages), 'with_text' => $withText]);

        if ($pages === [] || $withText === 0) {
            throw new \RuntimeException('O PDF não tem camada de texto (parece digitalizado); OCR está fora do escopo desta versão.');
        }

        $front = $analyzer->frontMatter($import->absolutePath(), $pages);
        SyncLog::record(null, SyncLog::OUT, 'import.front_matter', $import,
            sprintf('Obra "%s": %d pessoa(s), sumário com %d capítulo(s)', $front['data']['title'] ?? '?',
                count($front['data']['contributors'] ?? []), array_sum(array_map(fn ($s) => count($s['chapters'] ?? []), $front['data']['toc'] ?? []))),
            ['mode' => $front['mode'], 'pages' => $front['pages'], 'usage' => $analyzer->usage->toArray()['by_stage']['front_matter'] ?? null]);

        // 2. Segmentação -----------------------------------------------------
        $import->forceFill(['status' => Import::SEGMENTING])->save();

        $segmentation = $analyzer->segment($pages, $front['data']);
        if ($segmentation['chapters'] === []) {
            throw new \RuntimeException('A IA não encontrou sumário com capítulos nas páginas iniciais; confira se o PDF está completo.');
        }
        $located = count(array_filter($segmentation['chapters'], fn ($c) => $c['pdf_first_page'] !== null));
        SyncLog::record(null, SyncLog::IN, 'import.segmented', $import,
            sprintf('%d capítulo(s) no sumário, %d localizado(s) no texto, deslocamento de página %s', count($segmentation['chapters']), $located,
                $segmentation['offset'] === null ? 'desconhecido' : (string) $segmentation['offset']),
            ['offset' => $segmentation['offset'], 'anchors' => $segmentation['anchors']]);

        $meta = ['original_name' => $import->original_name, 'pages' => count($pages), 'pages_with_text' => $withText];
        $bookContributors = $analyzer->bookContributors($front['data']);
        $bookTitle = (string) ($front['data']['title'] ?? $import->original_name);

        $chapters = [];
        $import->forceFill(['result' => $analyzer->buildResult($meta, $front, $segmentation, $chapters)])->save();

        foreach ($segmentation['chapters'] as $seg) {
            $chapter = $analyzer->chapter($pages, $seg, $bookContributors, $bookTitle);
            $chapters[] = $chapter;
            if ($chapter['status'] === 'failed') {
                SyncLog::record(null, SyncLog::OUT, 'import.chapter', $import,
                    sprintf('Capítulo %d "%s" ficou para revisão: %s', $seg['sequence'], $seg['title'], $chapter['error']),
                    ['sequence' => $seg['sequence']], 'warning');
            }
            $import->forceFill([
                'result' => $analyzer->buildResult($meta, $front, $segmentation, $chapters),
                'usage' => $analyzer->usage->toArray(),
            ])->save();
        }

        // 3. Proposta --------------------------------------------------------
        $result = $analyzer->buildResult($meta, $front, $segmentation, $chapters);
        $usage = $analyzer->usage->toArray();
        $import->forceFill(['status' => Import::SUGGESTED, 'result' => $result, 'usage' => $usage, 'error' => null])->save();

        SyncLog::record(null, SyncLog::OUT, 'import.suggested', $import,
            sprintf('Proposta pronta: %d capítulo(s), %d analisado(s), %d para revisão; %d chamada(s), US$ %s',
                $result['summary']['chapters'], $result['summary']['analyzed'], $result['summary']['needs_review'],
                $usage['calls'], number_format($usage['cost_usd'], 2, ',', '.')),
            ['summary' => $result['summary'], 'usage' => array_diff_key($usage, ['calls_log' => 1])]);
    }
}
