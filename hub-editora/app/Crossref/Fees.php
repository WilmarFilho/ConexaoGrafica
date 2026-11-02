<?php

namespace App\Crossref;

use App\Models\Book;

/**
 * Custo estimado do registro na Crossref (tabela de 2026, crossref.org/fees):
 * por registro novo, livro US$ 1,00 e capítulo US$ 0,25; obra "back-year"
 * (publicada antes dos dois anos anteriores) US$ 0,15 cada. Atualizar um DOI
 * já registrado não é cobrado. A Crossref fatura por e-mail, a cada trimestre,
 * com 45 dias para pagar (cartão no portal ou transferência, sempre em dólar);
 * a anuidade vem à parte, em janeiro. Os valores ficam em config/hub.php
 * (crossref.fees) para acompanhar a tabela de 2027, que baixa os preços.
 */
class Fees
{
    /**
     * @return array{book: int, chapters: int, book_fee: float, chapter_fee: float, total: float, back_year: bool}
     */
    public static function estimate(Book $book): array
    {
        $book->loadMissing('chapters');
        $fees = (array) config('hub.crossref.fees');
        $backYear = (int) ($book->publication_year ?: date('Y')) < (int) date('Y') - 2;

        $bookFee = (float) ($backYear ? $fees['back_year'] : $fees['book']);
        $chapterFee = (float) ($backYear ? $fees['back_year'] : $fees['chapter']);

        // só o que ainda não foi registrado gera cobrança
        $newBook = $book->doi_status === 'registered' ? 0 : 1;
        $newChapters = $book->chapters->where('doi_status', '!=', 'registered')->count();

        return [
            'book' => $newBook,
            'chapters' => $newChapters,
            'book_fee' => $bookFee,
            'chapter_fee' => $chapterFee,
            'total' => round($newBook * $bookFee + $newChapters * $chapterFee, 2),
            'back_year' => $backYear,
        ];
    }

    /** "US$ 1,00 (livro) + 12 × US$ 0,25 (capítulos) = US$ 4,00" */
    public static function summary(Book $book): string
    {
        $e = self::estimate($book);

        if ($e['total'] <= 0) {
            return 'US$ 0,00 (os DOIs já estão registrados; atualização não é cobrada)';
        }

        $parts = [];

        if ($e['book']) {
            $parts[] = self::usd($e['book_fee']).' (livro)';
        }

        if ($e['chapters']) {
            $parts[] = $e['chapters'].' × '.self::usd($e['chapter_fee']).' ('.($e['chapters'] > 1 ? 'capítulos' : 'capítulo').')';
        }

        return implode(' + ', $parts).' = '.self::usd($e['total']).($e['back_year'] ? ' · tarifa de obra antiga' : '');
    }

    public static function usd(float $value): string
    {
        return 'US$ '.number_format($value, 2, ',', '.');
    }
}
