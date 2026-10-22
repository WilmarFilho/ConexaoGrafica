<?php

namespace App\Console\Commands;

use App\Editorial\Documents\PublishSheets;
use App\Editorial\Documents\SheetGenerator;
use App\Models\Book;
use Illuminate\Console\Command;
use Throwable;

/**
 * Gera em lote as fichas PDF de um livro (e de cada capítulo aprovado) e,
 * com --publicar, envia ao site na hora, sem passar pela fila.
 *
 *   php artisan hub:fichas 12
 *   php artisan hub:fichas 12 --forcar --publicar
 *   php artisan hub:fichas todos
 */
class SheetsCommand extends Command
{
    protected $signature = 'hub:fichas {book : id ou slug do livro, ou "todos" (publicados)}
                            {--publicar : envia as fichas ao WordPress depois de gerar}
                            {--forcar : regenera mesmo sem mudança nos dados}';

    protected $description = 'Gera as fichas científicas em PDF do livro e dos capítulos aprovados';

    public function handle(SheetGenerator $generator): int
    {
        $key = (string) $this->argument('book');
        $books = $key === 'todos'
            ? Book::query()->whereIn('status', ['published', 'unpublished'])->orderBy('id')->get()
            : Book::query()->when(ctype_digit($key), fn ($q) => $q->whereKey((int) $key), fn ($q) => $q->where('slug', $key))->get();

        if ($books->isEmpty()) {
            $this->error('Livro não encontrado.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('forcar');
        $failed = 0;

        foreach ($books as $book) {
            $this->info("Livro {$book->id} — {$book->title}");

            try {
                $doc = $generator->forBook($book, $force);
                $this->line("  ficha do livro: {$doc->absolutePath()} (".($doc->wasRecentlyCreated || $doc->wasChanged() ? 'gerada' : 'sem mudança').')');

                foreach ($book->chapters()->where('status', 'approved')->get() as $chapter) {
                    $chapter->setRelation('book', $book);
                    $d = $generator->forChapter($chapter, $force);
                    $this->line("  capítulo {$chapter->sequence}: {$d->absolutePath()} (".($d->wasRecentlyCreated || $d->wasChanged() ? 'gerada' : 'sem mudança').')');
                }
            } catch (Throwable $e) {
                $this->error('  falhou: '.$e->getMessage());
                $failed++;

                continue;
            }

            if ($this->option('publicar')) {
                try {
                    (new PublishSheets($book->id, $force))->handle($generator);
                    $this->info('  fichas enviadas ao site.');
                } catch (Throwable $e) {
                    $this->error('  envio ao site falhou: '.$e->getMessage());
                    $failed++;
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
