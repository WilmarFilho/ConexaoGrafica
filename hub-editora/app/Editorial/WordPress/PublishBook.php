<?php

namespace App\Editorial\WordPress;

use App\Editorial\Documents\PublishSheets;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Person;
use App\Models\SyncLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Publica (ou republica) o livro no WordPress: monta o payload completo,
 * chama o `PUT` assinado e grava de volta `wp_post_id`, `landing_url` e
 * `wp_term_id`. Essas URLs são as que vão para a Crossref.
 */
class PublishBook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    /** @param  bool  $sheets  false quando só o estado mudou (ex.: resultado da Crossref): as fichas não mudam */
    public function __construct(public int $bookId, public ?int $userId = null, public bool $sheets = true) {}

    public function handle(): void
    {
        $book = Book::query()->find($this->bookId);

        if (! $book) {
            return;
        }

        if (! $book->canBePublished()) {
            SyncLog::record(null, SyncLog::OUT, 'publish.failed', $book,
                "Livro em '{$book->statusLabel()}' não pode ir ao site: só aprovados", ['status' => $book->status], 'warning');

            return;
        }

        try {
            $payload = Payload::forBook($book, status: 'published');
            $result = Client::fromConfig()->publish($book->id, $payload);

            self::applyResult($book, $result);

            $book->forceFill([
                'status' => 'published',
                'published_at' => $book->published_at ?? now(),
            ])->save();

            SyncLog::record(null, SyncLog::OUT, 'publish.sent', $book,
                'Livro publicado no site: '.($book->landing_url ?: 'sem URL devolvida'),
                ['wp_post_id' => $book->wp_post_id, 'chapters' => count($payload['chapters']), 'user_id' => $this->userId]);

            // Fichas PDF (frente E): geradas com as URLs recém-devolvidas e enviadas em seguida.
            if ($this->sheets && config('hub.documents.auto_publish')) {
                PublishSheets::dispatch($book->id, false, $this->userId);
            }
        } catch (Throwable $e) {
            SyncLog::record(null, SyncLog::OUT, 'publish.failed', $book, $e->getMessage(), ['user_id' => $this->userId, 'attempt' => $this->attempts()], 'error');
            throw $e; // a fila tenta de novo
        }
    }

    /** Resposta do PUT (contrato, seção 4): ids e URLs do livro, dos capítulos e das pessoas. */
    public static function applyResult(Book $book, array $result): void
    {
        $b = $result['book'] ?? [];
        $book->forceFill([
            'wp_post_id' => isset($b['wp_post_id']) ? (int) $b['wp_post_id'] : $book->wp_post_id,
            'landing_url' => $b['url'] ?? $book->landing_url,
        ])->save();

        foreach ((array) ($result['chapters'] ?? []) as $c) {
            if (empty($c['hub_chapter_id'])) {
                continue;
            }
            Chapter::query()->where('book_id', $book->id)->whereKey($c['hub_chapter_id'])
                ->update(array_filter([
                    'wp_post_id' => isset($c['wp_post_id']) ? (int) $c['wp_post_id'] : null,
                    'landing_url' => $c['url'] ?? null,
                ], fn ($v) => $v !== null));
        }

        foreach ((array) ($result['people'] ?? []) as $p) {
            if (! empty($p['hub_person_id']) && isset($p['wp_term_id'])) {
                Person::query()->whereKey($p['hub_person_id'])->update(['wp_term_id' => (int) $p['wp_term_id']]);
            }
        }
    }
}
