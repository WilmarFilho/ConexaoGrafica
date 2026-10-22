<?php

namespace App\Editorial\WordPress;

use App\Models\Book;
use App\Models\SyncLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Tira o livro de catálogo no site (`POST …/despublicar`): some o botão de
 * compra e a página ganha o aviso, mas continua no ar (regra da Crossref).
 */
class UnpublishBook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $bookId, public ?int $userId = null) {}

    public function handle(): void
    {
        $book = Book::query()->find($this->bookId);

        if (! $book || $book->status !== 'published') {
            return;
        }

        try {
            Client::fromConfig()->unpublish($book->id);

            $book->forceFill(['status' => 'unpublished'])->save();

            SyncLog::record(null, SyncLog::OUT, 'publish.unpublished', $book, 'Livro marcado fora de catálogo no site', ['user_id' => $this->userId]);
        } catch (Throwable $e) {
            SyncLog::record(null, SyncLog::OUT, 'publish.failed', $book, 'Retirada de catálogo falhou: '.$e->getMessage(), ['user_id' => $this->userId], 'error');
            throw $e;
        }
    }
}
