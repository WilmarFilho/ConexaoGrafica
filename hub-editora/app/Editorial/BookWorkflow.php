<?php

namespace App\Editorial;

use App\Editorial\WordPress\Client;
use App\Editorial\WordPress\PublishBook;
use App\Editorial\WordPress\UnpublishBook;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Contribution;
use App\Models\SyncLog;
use App\Models\User;
use App\Rules\Isbn13;
use Illuminate\Support\Facades\DB;

/**
 * Estados e transições da seção 3 do contrato.
 *
 *   Livro:    draft → (checklist OK) review → (revisor aprova) approved → (publicar) published ⇄ unpublished
 *   Capítulo: draft → review → approved
 *
 * Toda transição passa por aqui e deixa rastro no SyncLog (`editorial.*`).
 * As telas só chamam; quem decide é este serviço.
 */
class BookWorkflow
{
    // ---- Checklist ----------------------------------------------------------

    /**
     * Itens conferidos antes de cada passo. `stage` diz quando o item trava:
     * `review` (para enviar à revisão), `approve` (para aprovar e publicar) ou
     * `info` (só aviso).
     *
     * @return array<int, array{key: string, label: string, ok: bool, stage: string, detail: ?string}>
     */
    public static function checklist(Book $book): array
    {
        $book->loadMissing(['contributions.person', 'contributions.institution', 'chapters.contributions.person', 'commercialLinks']);

        $items = [];
        $add = function (string $key, string $label, bool $ok, string $stage = 'review', ?string $detail = null) use (&$items) {
            $items[] = compact('key', 'label', 'ok', 'stage', 'detail');
        };

        $add('title', 'Título', filled($book->title));
        $add('year', 'Ano de publicação', (int) $book->publication_year >= 1900);
        $add('publisher', 'Editora e local', filled($book->publisher) && filled($book->publication_place));
        $add('subject', 'Área de conhecimento', filled($book->subject_area));
        $add('abstract', 'Resumo', filled($book->abstract));

        $isbns = $book->allIsbns();
        $invalid = array_filter($isbns, fn ($i) => ! Isbn13::isValid($i));
        $add('isbn', 'ISBN-13', $isbns !== [] && $invalid === [], 'review',
            $isbns === [] ? 'Informe ao menos um ISBN.' : ($invalid ? 'Inválido: '.implode(', ', $invalid) : null));

        $doiOk = filled($book->doi_prefix) && DoiSuffix::isWellFormed($book->doi_suffix)
            && DoiSuffix::isAvailable($book->doi_prefix, $book->doi_suffix, $book);
        $add('doi', 'Prefixo e sufixo DOI', $doiOk, 'review', $doiOk ? $book->doi : 'Sufixo inválido ou já usado.');

        $unpublishable = $book->contributions->reject(fn (Contribution $c) => $c->isPublishable());
        $add('contributors', 'Organizadores/autores com ORCID ou instituição',
            $book->contributions->isNotEmpty() && $unpublishable->isEmpty(), 'review',
            $book->contributions->isEmpty() ? 'Nenhuma pessoa vinculada.' : ($unpublishable->isNotEmpty()
                ? 'Sem ORCID nem instituição: '.$unpublishable->map(fn ($c) => $c->person?->name)->implode(', ')
                : null));

        $add('keywords', 'Palavras-chave', count($book->keywords ?? []) > 0, 'info');

        $chapters = $book->chapters;
        $add('chapters', 'Capítulos cadastrados', $chapters->isNotEmpty(), $book->type === 'edited_book' ? 'review' : 'info',
            $chapters->isEmpty() ? 'Nenhum capítulo.' : $chapters->count().' capítulo(s)');

        if ($chapters->isNotEmpty()) {
            $incomplete = $chapters->filter(fn (Chapter $c) => self::chapterIssues($c) !== []);
            $add('chapters_complete', 'Capítulos completos (título, páginas, autores com ORCID ou instituição)',
                $incomplete->isEmpty(), 'review',
                $incomplete->isNotEmpty() ? 'Pendentes: '.$incomplete->map(fn ($c) => $c->sequence.' ('.implode('; ', self::chapterIssues($c)).')')->implode(' · ') : null);

            $notApproved = $chapters->where('status', '!=', 'approved');
            $add('chapters_approved', 'Capítulos aprovados', $notApproved->isEmpty(), 'approve',
                $notApproved->isNotEmpty() ? $notApproved->count().' ainda não aprovado(s)' : null);
        }

        $add('commercial', 'Link de compra (produto Woo ou URL)',
            $book->commercialLinks->contains(fn ($l) => $l->enabled && $l->hasDestination()), 'info',
            'Sem link a página fica no ar, só sem o botão de compra.');

        return $items;
    }

    /** O que falta num capítulo para ele ser publicável. */
    public static function chapterIssues(Chapter $chapter): array
    {
        $chapter->loadMissing('contributions.person');
        $issues = [];

        if (blank($chapter->title)) {
            $issues[] = 'sem título';
        }
        if ($chapter->first_page < 1 || $chapter->last_page < $chapter->first_page) {
            $issues[] = 'páginas';
        }
        if ($chapter->contributions->isEmpty()) {
            $issues[] = 'sem autores';
        } elseif ($chapter->contributions->contains(fn (Contribution $c) => ! $c->isPublishable())) {
            $issues[] = 'autor sem ORCID nem instituição';
        }
        if (! DoiSuffix::isWellFormed($chapter->doi_suffix)) {
            $issues[] = 'sufixo DOI';
        }

        return $issues;
    }

    /** Rótulos dos itens pendentes para o estágio (e os anteriores). */
    public static function pending(Book $book, string $stage): array
    {
        $stages = $stage === 'approve' ? ['review', 'approve'] : ['review'];

        return collect(self::checklist($book))
            ->filter(fn ($i) => ! $i['ok'] && in_array($i['stage'], $stages, true))
            ->pluck('label')->all();
    }

    public static function isReadyForReview(Book $book): bool
    {
        return self::pending($book, 'review') === [];
    }

    // ---- Transições do livro ------------------------------------------------

    public static function submitForReview(Book $book, ?User $user = null): void
    {
        self::assertStatus($book, ['draft'], 'Só rascunhos vão para revisão');

        if ($pending = self::pending($book, 'review')) {
            throw new EditorialException('Checklist pendente: '.implode(', ', $pending).'.');
        }

        self::transition($book, 'review', 'editorial.review_requested', 'Livro enviado para revisão', $user);
    }

    public static function approve(Book $book, ?User $user = null): void
    {
        $user ??= auth()->user();

        if (! $user?->canApproveEditorial()) {
            throw new EditorialException('Só revisores e administradores aprovam livros.');
        }

        self::assertStatus($book, ['review'], 'Só livros em revisão podem ser aprovados');

        if ($pending = self::pending($book, 'approve')) {
            throw new EditorialException('Checklist pendente: '.implode(', ', $pending).'.');
        }

        $book->approved_by = $user->id;
        $book->approved_at = now();
        self::transition($book, 'approved', 'editorial.approved', 'Livro aprovado por '.$user->name, $user);
    }

    /** Revisor devolve (ou o editor retira) o livro para ajustes. */
    public static function backToDraft(Book $book, ?User $user = null, ?string $reason = null): void
    {
        $user ??= auth()->user();

        if (! $user?->canEditEditorial()) {
            throw new EditorialException('Seu perfil não altera a situação do livro.');
        }

        self::assertStatus($book, ['review', 'approved'], 'Só livros em revisão ou aprovados voltam ao rascunho');

        $book->approved_by = null;
        $book->approved_at = null;
        self::transition($book, 'draft', 'editorial.returned', 'Livro devolvido ao rascunho'.($reason ? ': '.$reason : ''), $user, ['reason' => $reason]);
    }

    /** Enfileira a publicação (ou republicação) no WordPress. Só `approved` (ou já publicado) vai ao site. */
    public static function requestPublish(Book $book, ?User $user = null): void
    {
        $user ??= auth()->user();

        if (! $user?->canEditEditorial()) {
            throw new EditorialException('Seu perfil não publica livros.');
        }

        self::assertStatus($book, Book::PUBLISHABLE, 'Só livros aprovados vão para o site');

        if (! Client::isConfigured()) {
            throw new EditorialException('Site não configurado: defina CONEXAO_DOI_WP_URL e CONEXAO_DOI_WP_SECRET no .env do Hub.');
        }

        PublishBook::dispatch($book->id, $user->id);
        SyncLog::record(null, SyncLog::MANUAL, 'editorial.publish_requested', $book,
            ($book->isPublished() ? 'Republicação' : 'Publicação').' no site solicitada', ['status' => $book->status]);
    }

    /** Marca fora de catálogo no site (a página continua no ar, regra da Crossref). */
    public static function requestUnpublish(Book $book, ?User $user = null): void
    {
        $user ??= auth()->user();

        if (! $user?->canEditEditorial()) {
            throw new EditorialException('Seu perfil não altera a situação do livro.');
        }

        self::assertStatus($book, ['published'], 'Só livros publicados saem de catálogo');

        if (! Client::isConfigured()) {
            throw new EditorialException('Site não configurado: defina CONEXAO_DOI_WP_URL e CONEXAO_DOI_WP_SECRET no .env do Hub.');
        }

        UnpublishBook::dispatch($book->id, $user->id);
        SyncLog::record(null, SyncLog::MANUAL, 'editorial.unpublish_requested', $book, 'Retirada de catálogo solicitada');
    }

    // ---- Capítulos ----------------------------------------------------------

    public static function submitChapterForReview(Chapter $chapter, ?User $user = null): void
    {
        if ($chapter->status !== 'draft') {
            throw new EditorialException('Só capítulos em rascunho vão para revisão.');
        }

        if ($issues = self::chapterIssues($chapter)) {
            throw new EditorialException('Capítulo incompleto: '.implode(', ', $issues).'.');
        }

        $chapter->update(['status' => 'review']);
        SyncLog::record(null, SyncLog::MANUAL, 'editorial.chapter_review_requested', $chapter,
            "Capítulo {$chapter->sequence} enviado para revisão", ['book_id' => $chapter->book_id]);
    }

    /**
     * Revisor aprova o capítulo. O resumo aprovado passa a ser o que será
     * publicado; se ainda não foi escolhido, fica o original (ou a sugestão da IA).
     */
    public static function approveChapter(Chapter $chapter, ?User $user = null): void
    {
        $user ??= auth()->user();

        if (! $user?->canApproveEditorial()) {
            throw new EditorialException('Só revisores e administradores aprovam capítulos.');
        }

        if ($chapter->status === 'approved') {
            return;
        }

        if ($issues = self::chapterIssues($chapter)) {
            throw new EditorialException('Capítulo incompleto: '.implode(', ', $issues).'.');
        }

        $chapter->update([
            'abstract_approved' => $chapter->abstract_approved ?: $chapter->bestAbstract(),
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        SyncLog::record(null, SyncLog::MANUAL, 'editorial.chapter_approved', $chapter,
            "Capítulo {$chapter->sequence} aprovado por {$user->name}", ['book_id' => $chapter->book_id]);
    }

    /** Recalcula os sufixos DOI dos capítulos ainda sem DOI validado, pela ordem atual. */
    public static function renumberChapterSuffixes(Book $book): int
    {
        $chapters = $book->chapters()->where('doi_status', 'none')->orderBy('sequence')->get();

        DB::transaction(function () use ($book, $chapters) {
            // Duas passadas para não esbarrar na unicidade (book_id, doi_suffix) durante a troca.
            foreach ($chapters as $c) {
                $c->update(['doi_suffix' => 'tmp-'.$c->id]);
            }
            foreach ($chapters as $c) {
                $c->update(['doi_suffix' => DoiSuffix::forChapter($book, (int) $c->sequence)]);
            }
        });

        if ($chapters->isNotEmpty()) {
            SyncLog::record(null, SyncLog::MANUAL, 'editorial.chapters_renumbered', $book, $chapters->count().' capítulo(s) renumerado(s)');
        }

        return $chapters->count();
    }

    // ---- Internos -----------------------------------------------------------

    private static function assertStatus(Book $book, array $allowed, string $message): void
    {
        if (! in_array($book->status, $allowed, true)) {
            throw new EditorialException("{$message} (situação atual: {$book->statusLabel()}).");
        }
    }

    private static function transition(Book $book, string $to, string $action, string $message, ?User $user, array $context = []): void
    {
        $from = $book->status;
        $book->status = $to;
        $book->save();

        SyncLog::record(null, SyncLog::MANUAL, $action, $book, $message, ['from' => $from, 'to' => $to, 'user' => $user?->email] + $context);
    }
}
