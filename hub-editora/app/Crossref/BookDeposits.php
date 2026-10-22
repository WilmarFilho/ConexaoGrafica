<?php

namespace App\Crossref;

use App\Editorial\EditorialException;
use App\Editorial\WordPress\Payload;
use App\Jobs\SendDeposit;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Deposit;
use App\Models\DepositItem;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Liga o módulo Editorial (livro e capítulos) ao depósito na Crossref.
 *
 *   register()  livro `published` com URL pública em tudo → payload do contrato
 *               (o mesmo do WordPress, mais as `landing_url` devolvidas pelo site)
 *               → DepositService::create (XML validado no XSD = `validated`) →
 *               `ready` em livro e capítulos → SendDeposit na fila.
 *   sync()      lê os `deposit_items` e espelha o estado no `doi_status` de livro e
 *               capítulos: sent → processing → registered | failed. Chamado ao fim
 *               de SendDeposit, RefreshDeposit e dos comandos hub:crossref-*.
 *
 * Reenvio: livro `registered` gera um depósito novo com os mesmos DOIs (atualização
 * de metadados ou de URL, contrato seção 3; o sufixo nunca muda). Livro `sent` ou
 * `processing` espera o resultado antes de registrar de novo.
 */
class BookDeposits
{
    public function __construct(private readonly DepositService $service) {}

    public static function make(?DepositService $service = null): self
    {
        return new self($service ?? DepositService::make());
    }

    // ---- Registro ------------------------------------------------------------

    /**
     * Gera o depósito do livro (todos os DOIs: o do livro e um por capítulo) e o
     * põe na fila de envio.
     *
     * @throws EditorialException livro não publicado, sem URL, já em andamento, perfil sem permissão ou XML fora do schema
     */
    public function register(Book $book, User $by): Deposit
    {
        if (! $by->canDeposit()) {
            throw new EditorialException('Seu perfil não registra DOI na Crossref.');
        }

        self::assertRegistrable($book);

        try {
            $deposit = $this->service->create(self::payload($book), $book->id, $by->id);
        } catch (CrossrefException $e) {
            throw new EditorialException($e->getMessage(), previous: $e);
        }

        $chapterIds = $book->chapters->pluck('id')->all();

        // XML passou no XSD (`validated`) e o depósito está gravado para envio (`ready`).
        DB::transaction(function () use ($book, $chapterIds) {
            foreach (['validated', 'ready'] as $state) {
                self::mark($book->id, $chapterIds, $state);
            }
        });
        $book->refresh();

        $update = $book->deposits()->count() > 1;
        SyncLog::record(null, SyncLog::MANUAL, 'deposit.requested', $book,
            sprintf('%s de %d DOI(s) na Crossref (%s) solicitado por %s: lote %s',
                $update ? 'Atualização' : 'Registro', $deposit->items->count(), $deposit->environment, $by->name, $deposit->batch_id),
            ['deposit_id' => $deposit->id, 'batch_id' => $deposit->batch_id, 'environment' => $deposit->environment, 'update' => $update]);

        SendDeposit::dispatch($deposit->id);

        return $deposit;
    }

    /** O que impede o registro, em português; `null` se o livro pode ir. */
    public static function blocker(Book $book): ?string
    {
        $book->loadMissing('chapters');

        if ($book->status !== 'published') {
            return "Só livros publicados no site são registrados na Crossref (situação atual: {$book->statusLabel()}).";
        }

        if (blank($book->landing_url)) {
            return 'O livro ainda não tem a URL pública devolvida pelo site. Publique (ou republique) no site antes de registrar o DOI.';
        }

        $semUrl = $book->chapters->filter(fn (Chapter $c) => blank($c->landing_url))->pluck('sequence');
        if ($semUrl->isNotEmpty()) {
            return 'Capítulo(s) sem URL pública: '.$semUrl->implode(', ').'. Republique o livro no site para o WordPress devolver as URLs.';
        }

        if (in_array($book->doi_status, ['sent', 'processing'], true)) {
            return "Já existe um depósito em andamento ({$book->doiStatusLabel()}). Consulte o resultado antes de registrar de novo.";
        }

        if (! Client::isConfigured()) {
            return 'Crossref não configurada: defina CROSSREF_LOGIN e CROSSREF_PASSWORD no .env do Hub.';
        }

        return null;
    }

    /** @throws EditorialException */
    public static function assertRegistrable(Book $book): void
    {
        if ($why = self::blocker($book)) {
            throw new EditorialException($why);
        }
    }

    /**
     * Payload do contrato (o mesmo que vai ao WordPress) com as URLs públicas que
     * o site devolveu: o XmlBuilder prefere `landing_url` ao slug. São essas as
     * URLs que vão para a Crossref (contrato, seção 4).
     */
    public static function payload(Book $book): array
    {
        $payload = Payload::forBook($book);
        $payload['book']['landing_url'] = $book->landing_url;

        $urls = $book->chapters->pluck('landing_url', 'id');
        foreach ($payload['chapters'] as $i => $chapter) {
            $payload['chapters'][$i]['landing_url'] = $urls[$chapter['hub_id']] ?? null;
        }

        return $payload;
    }

    /**
     * DOIs que entram no lote do livro, na ordem do XML.
     *
     * @return list<array{target_type: string, target_id: int, label: string, doi: string}>
     */
    public static function dois(Book $book): array
    {
        $book->loadMissing('chapters');

        $out = [['target_type' => DepositItem::BOOK, 'target_id' => $book->id, 'label' => $book->title, 'doi' => $book->doi]];
        foreach ($book->chapters as $c) {
            $out[] = ['target_type' => DepositItem::CHAPTER, 'target_id' => $c->id, 'label' => "Cap. {$c->sequence} · {$c->title}", 'doi' => $book->doi_prefix.'/'.$c->doi_suffix];
        }

        return $out;
    }

    public static function latestDeposit(Book $book): ?Deposit
    {
        return $book->deposits()->latest('id')->first();
    }

    // ---- Sincronização --------------------------------------------------------

    /**
     * Espelha o depósito no `doi_status` do livro e dos capítulos. Só o depósito
     * mais recente do livro manda: o resultado atrasado de um lote antigo não
     * sobrescreve um reenvio. Devolve o número de registros alterados.
     */
    public function sync(Deposit $deposit): int
    {
        $book = Book::find($deposit->book_id);
        if (! $book) {
            return 0;
        }

        if ($deposit->id !== (int) $book->deposits()->max('id')) {
            return 0;
        }

        // Item sem resultado individual segue o estado do lote.
        $batchState = match ($deposit->status) {
            Deposit::QUEUED => 'ready',
            Deposit::SENT => 'sent',
            Deposit::PROCESSING => 'processing',
            Deposit::DONE => 'registered',
            Deposit::FAILED => 'failed',
            default => null,
        };
        if ($batchState === null) {
            return 0;
        }

        $deposit->loadMissing('items');

        $byState = [];
        foreach ($deposit->items as $item) {
            $state = match ($item->status) {
                DepositItem::REGISTERED => 'registered',
                DepositItem::FAILED => 'failed',
                default => $batchState,
            };
            $byState[$state][$item->target_type][] = (int) $item->target_id;
        }

        $changed = 0;
        DB::transaction(function () use ($byState, $book, &$changed) {
            foreach ($byState as $state => $targets) {
                if (in_array($book->id, $targets[DepositItem::BOOK] ?? [], true)) {
                    $changed += Book::whereKey($book->id)->where('doi_status', '!=', $state)->update(['doi_status' => $state]);
                }
                if ($ids = $targets[DepositItem::CHAPTER] ?? []) {
                    $changed += Chapter::whereIn('id', $ids)->where('book_id', $book->id)->where('doi_status', '!=', $state)->update(['doi_status' => $state]);
                }
            }
        });

        if ($changed > 0) {
            $counts = collect($byState)->map(fn ($t) => count($t[DepositItem::BOOK] ?? []) + count($t[DepositItem::CHAPTER] ?? []));
            SyncLog::record(null, SyncLog::IN, 'deposit.synced', $book,
                sprintf('DOIs do livro atualizados pelo depósito %s (%s): %s', $deposit->batch_id, $deposit->status_label,
                    $counts->map(fn ($n, $s) => "{$n} ".mb_strtolower(Book::DOI_STATUSES[$s] ?? $s))->implode(', ')),
                ['deposit_id' => $deposit->id, 'deposit_status' => $deposit->status, 'changed' => $changed, 'counts' => $counts->all()]);
        }

        return $changed;
    }

    private static function mark(int $bookId, array $chapterIds, string $state): void
    {
        Book::whereKey($bookId)->update(['doi_status' => $state]);
        if ($chapterIds !== []) {
            Chapter::whereIn('id', $chapterIds)->update(['doi_status' => $state]);
        }
    }
}
