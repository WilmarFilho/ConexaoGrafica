<?php

namespace App\Console\Commands;

use App\Crossref\Client;
use App\Models\Book;
use App\Models\Deposit;
use App\Models\SyncLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Virada da Crossref de homologação para produção (CROSSREF_ENV=production).
 * O doi_status do livro e dos capítulos espelha o último depósito, e os de
 * teste não existem em produção: quem só foi registrado em homologação volta
 * para "Validado" (o sufixo continua travado), o custo passa a contar e o
 * próximo envio é um registro novo, não uma atualização.
 *
 *   php artisan hub:crossref-producao            mostra o que mudaria
 *   php artisan hub:crossref-producao --aplicar  grava
 */
class CrossrefGoLiveCommand extends Command
{
    protected $signature = 'hub:crossref-producao {--aplicar : Grava as mudanças (sem isso, só mostra)}';

    protected $description = 'Depois de trocar CROSSREF_ENV para production, tira dos livros o registro que só existe em homologação';

    public function handle(): int
    {
        if (! Client::fromConfig()->isProduction()) {
            $this->error('O Hub ainda está em homologação (CROSSREF_ENV=test). Troque para production e rode config:cache antes.');

            return self::FAILURE;
        }

        $books = Book::query()->whereIn('doi_status', ['sent', 'processing', 'registered', 'failed'])->get()
            ->filter(fn (Book $book) => ! Deposit::query()->where('book_id', $book->id)->where('environment', Deposit::PRODUCTION)->exists());

        if ($books->isEmpty()) {
            $this->info('Nada a mudar: nenhum livro com registro só de homologação.');

            return self::SUCCESS;
        }

        foreach ($books as $book) {
            $this->line(sprintf('Livro %d · %s · %s → Validado (%d capítulo(s))', $book->id, $book->title, $book->doiStatusLabel(), $book->chapters()->count()));
        }

        if (! $this->option('aplicar')) {
            $this->warn('Nada gravado. Rode de novo com --aplicar.');

            return self::SUCCESS;
        }

        foreach ($books as $book) {
            DB::transaction(function () use ($book) {
                $book->chapters()->whereIn('doi_status', ['sent', 'processing', 'registered', 'failed'])->update(['doi_status' => 'validated']);
                $book->forceFill(['doi_status' => 'validated'])->saveQuietly();
            });

            SyncLog::record(null, SyncLog::OUT, 'crossref.go_live', $book,
                'Crossref em produção: registro de homologação desconsiderado; o próximo envio registra os DOIs de verdade');
        }

        $this->info($books->count().' livro(s) prontos para o registro em produção.');

        return self::SUCCESS;
    }
}
