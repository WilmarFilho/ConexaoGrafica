<?php

namespace App\Editorial\Documents;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;

/**
 * Quando um campo público do livro ou do capítulo muda, as fichas já
 * enviadas ao site ficam `stale` (a página mostra uma versão antiga até o
 * próximo PublishSheets). Observer simples: só os campos do próprio registro;
 * pessoas e links comerciais mudam raramente e o hash pega na regeneração.
 */
class StaleSheetsObserver
{
    public const BOOK_FIELDS = [
        'title', 'subtitle', 'alt_title', 'type', 'series_title', 'series_issn', 'volume', 'edition_number',
        'language', 'publisher', 'publication_place', 'publication_year', 'publication_date', 'pages', 'format',
        'subject_area', 'subject_subarea', 'audience', 'isbn_print', 'isbn_ebook', 'other_isbns', 'doi_prefix',
        'doi_suffix', 'landing_url', 'abstract', 'abstract_en', 'synopsis', 'keywords', 'keywords_en', 'slug',
    ];

    public const CHAPTER_FIELDS = [
        'sequence', 'section', 'number', 'title', 'subtitle', 'title_en', 'language', 'first_page', 'last_page',
        'publication_date', 'abstract_approved', 'keywords', 'doi_suffix', 'landing_url', 'slug', 'status',
    ];

    public function updated(Book|Chapter $model): void
    {
        $fields = $model instanceof Book ? self::BOOK_FIELDS : self::CHAPTER_FIELDS;

        if (! $model->wasChanged($fields)) {
            return;
        }

        Document::markStale($model);

        // O sumário e as citações da ficha do livro usam os dados do capítulo.
        if ($model instanceof Chapter && $model->book_id) {
            Document::query()
                ->where('documentable_type', 'book')
                ->where('documentable_id', $model->book_id)
                ->where('stale', false)
                ->update(['stale' => true]);
        }
    }

    public function deleted(Book|Chapter $model): void
    {
        $docs = Document::query()
            ->where(fn ($q) => $q->where('documentable_type', $model->getMorphClass())->where('documentable_id', $model->getKey()))
            // Livro apagado leva os capítulos (cascade no banco) e as fichas deles.
            ->when($model instanceof Book, fn ($q) => $q->orWhere(fn ($w) => $w->where('documentable_type', 'chapter')
                ->whereIn('documentable_id', Chapter::query()->where('book_id', $model->getKey())->select('id'))))
            ->get();

        foreach ($docs as $doc) {
            if ($doc->fileExists()) {
                Storage::disk(Document::DISK)->delete($doc->path);
            }
            $doc->delete();
        }
    }
}
