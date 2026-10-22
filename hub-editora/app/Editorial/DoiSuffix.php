<?php

namespace App\Editorial;

use App\Models\Book;
use App\Models\Chapter;

/**
 * Sufixo do DOI gerado pelo sistema (contrato, seção 1):
 *   livro     conexao.{ano}.{NNN}    sequencial por ano, 3 dígitos
 *   capítulo  {sufixo do livro}.{NN}  ordem no livro, 2 dígitos (3 a partir de 100)
 *
 * O prefixo vem de CROSSREF_PREFIX (config hub.crossref.prefix); até a
 * editora informar o oficial vale o de demonstração 10.00000.
 */
class DoiSuffix
{
    public const DEFAULT_PREFIX = '10.00000';

    /** Formato aceito quando o editor altera o sufixo à mão (só em rascunho). */
    public const PATTERN = '/^[a-z0-9][a-z0-9._\-]{0,58}$/';

    public static function prefix(): string
    {
        return (string) (config('hub.crossref.prefix') ?: self::DEFAULT_PREFIX);
    }

    public static function isDemoPrefix(?string $prefix = null): bool
    {
        return ($prefix ?? self::prefix()) === self::DEFAULT_PREFIX;
    }

    /** Próximo sufixo livre do ano, no prefixo informado (ou no configurado). */
    public static function forBook(int $year, ?string $prefix = null): string
    {
        $prefix ??= self::prefix();
        $base = "conexao.{$year}.";

        $last = Book::query()
            ->where('doi_prefix', $prefix)
            ->where('doi_suffix', 'like', $base.'%')
            ->pluck('doi_suffix')
            ->map(fn (string $s) => preg_match('/^conexao\.\d{4}\.(\d+)$/', $s, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        return $base.str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
    }

    /** Sufixo do capítulo a partir da ordem no livro. */
    public static function forChapter(Book $book, int $sequence): string
    {
        $n = str_pad((string) $sequence, $sequence >= 100 ? 3 : 2, '0', STR_PAD_LEFT);

        return "{$book->doi_suffix}.{$n}";
    }

    public static function isWellFormed(?string $suffix): bool
    {
        return (bool) preg_match(self::PATTERN, (string) $suffix);
    }

    /** Nenhum livro nem capítulo usa este DOI (ignorando o próprio registro). */
    public static function isAvailable(string $prefix, string $suffix, ?Book $ignoreBook = null, ?Chapter $ignoreChapter = null): bool
    {
        $usedByBook = Book::query()->where('doi_prefix', $prefix)->where('doi_suffix', $suffix)
            ->when($ignoreBook?->exists, fn ($q) => $q->whereKeyNot($ignoreBook->getKey()))
            ->exists();

        if ($usedByBook) {
            return false;
        }

        return ! Chapter::query()
            ->where('doi_suffix', $suffix)
            ->whereHas('book', fn ($q) => $q->where('doi_prefix', $prefix))
            ->when($ignoreChapter?->exists, fn ($q) => $q->whereKeyNot($ignoreChapter->getKey()))
            ->exists();
    }
}
