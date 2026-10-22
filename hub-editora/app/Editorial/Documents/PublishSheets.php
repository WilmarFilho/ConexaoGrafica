<?php

namespace App\Editorial\Documents;

use App\Editorial\WordPress\Client;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Document;
use App\Models\SyncLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Gera as fichas do livro e de cada capítulo aprovado e envia ao WordPress
 * (`POST /documentos`, que já grava o PDF na página certa). Só sobe o que
 * está `stale` ou nunca foi enviado; com `$force`, regenera e reenvia tudo.
 * Encadeado no fim do PublishBook (depois do PUT) e disponível pela ação
 * "Regenerar e publicar fichas" e pelo comando hub:fichas --publicar.
 */
class PublishSheets implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 120;

    /** Um livro com 70+ capítulos leva alguns minutos no dompdf. */
    public int $timeout = 1200;

    public function __construct(public int $bookId, public bool $force = false, public ?int $userId = null) {}

    public function handle(SheetGenerator $generator): void
    {
        $book = Book::query()->find($this->bookId);

        if (! $book) {
            return;
        }

        if (! Client::isConfigured()) {
            SyncLog::record(null, SyncLog::OUT, 'document.failed', $book,
                'Fichas não enviadas: site não configurado (CONEXAO_DOI_WP_URL / CONEXAO_DOI_WP_SECRET).', [], 'warning');

            return;
        }

        $client = Client::fromConfig();
        $sent = 0;
        $skipped = 0;
        $errors = [];

        $upload = function (Document $doc, Book|Chapter $target) use ($client, $book, &$sent, &$skipped, &$errors) {
            if (! $this->force && ! $doc->stale && $doc->isPublished()) {
                $skipped++;

                return;
            }

            try {
                $result = $client->uploadDocument(
                    $book->id,
                    $doc->type,
                    $doc->filename(),
                    $doc->contents(),
                    $doc->template_version,
                    $target instanceof Chapter ? $target->id : null,
                );

                $doc->forceFill([
                    'wp_attachment_id' => isset($result['attachment_id']) ? (int) $result['attachment_id'] : null,
                    'public_url' => $result['url'] ?? null,
                    'stale' => false,
                ])->save();
                $sent++;
            } catch (Throwable $e) {
                $errors[] = ($target instanceof Chapter ? "Capítulo {$target->sequence}" : 'Livro').': '.$e->getMessage();
            }
        };

        try {
            $upload($generator->forBook($book, $this->force), $book);

            foreach ($book->chapters()->where('status', 'approved')->get() as $chapter) {
                $chapter->setRelation('book', $book);
                $upload($generator->forChapter($chapter, $this->force), $chapter);
            }
        } catch (Throwable $e) {
            SyncLog::record(null, SyncLog::OUT, 'document.failed', $book, 'Geração das fichas falhou: '.$e->getMessage(),
                ['user_id' => $this->userId, 'attempt' => $this->attempts()], 'error');
            throw $e;
        }

        if ($errors) {
            SyncLog::record(null, SyncLog::OUT, 'document.failed', $book,
                count($errors).' ficha(s) não enviada(s) ao site: '.implode(' · ', $errors),
                ['sent' => $sent, 'user_id' => $this->userId, 'attempt' => $this->attempts()], 'error');

            throw new \RuntimeException($errors[0]); // a fila tenta de novo; o que já subiu fica gravado
        }

        SyncLog::record(null, SyncLog::OUT, 'document.published', $book,
            "{$sent} ficha(s) enviada(s) ao site".($skipped ? ", {$skipped} já atualizada(s)" : ''),
            ['sent' => $sent, 'skipped' => $skipped, 'forced' => $this->force, 'user_id' => $this->userId]);
    }
}
