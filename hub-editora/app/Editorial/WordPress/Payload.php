<?php

namespace App\Editorial\WordPress;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\CommercialLink;
use App\Models\Contribution;
use App\Models\Document;

/**
 * Monta o corpo do `PUT /wp-json/conexao-doi/v1/publicacoes/{hub_book_id}`,
 * idêntico a `conexao-editora/docs/exemplos/publicacao-tratado.json`.
 * O Hub manda sempre o registro completo; o WP substitui, não mescla.
 * Datas em ISO-8601, texto sem HTML.
 */
class Payload
{
    /** @param  ?string  $status  situação a declarar ao site (o job de publicação manda `published` antes de gravar) */
    public static function forBook(Book $book, ?string $status = null): array
    {
        $book->loadMissing([
            'contributions.person', 'contributions.institution',
            'chapters.contributions.person', 'chapters.contributions.institution',
            'commercialLinks', 'documents', 'chapters.documents',
        ]);

        return [
            'book' => [
                'hub_id' => $book->id,
                'slug' => $book->slug,
                'title' => $book->title,
                'subtitle' => $book->subtitle,
                'alt_title' => $book->alt_title,
                'alt_title_lang' => $book->alt_title_lang,
                'type' => $book->type,
                'series' => filled($book->series_title) ? ['title' => $book->series_title, 'issn' => $book->series_issn] : null,
                'volume' => $book->volume,
                'edition_number' => $book->edition_number,
                'language' => $book->language,
                'publisher' => $book->publisher,
                'publication_place' => $book->publication_place,
                'publication_year' => (int) $book->publication_year,
                'publication_date' => $book->publication_date?->format('Y-m-d'),
                'pages' => $book->pages,
                'format' => $book->format,
                'subject_area' => $book->subject_area,
                'subject_subarea' => $book->subject_subarea,
                'audience' => $book->audience,
                'isbn_print' => $book->isbn_print,
                'isbn_ebook' => $book->isbn_ebook,
                'doi' => $book->doi,
                'keywords' => self::strings($book->keywords),
                'keywords_en' => self::strings($book->keywords_en),
                'abstract' => $book->abstract,
                'synopsis' => $book->synopsis,
                'references' => self::references($book->references),
                'contributors' => $book->contributions->map(fn (Contribution $c) => self::contributor($c))->values()->all(),
                'toc' => self::toc($book),
                'commercial' => $book->commercialLinks->map(fn (CommercialLink $l) => self::commercial($l))->values()->all(),
                // Fichas PDF já publicadas no site (frente E); sem nenhuma, vai o registro vazio.
                'documents' => self::documents($book->documents) ?: [self::documentStub('book_sheet')],
                'status' => $status ?? $book->status,
                // O WP trava o slug a partir de `sent` (decisão da frente B).
                'doi_status' => $book->doi_status,
            ],
            'chapters' => $book->chapters->map(fn (Chapter $c) => self::chapter($c, $book))->values()->all(),
        ];
    }

    public static function chapter(Chapter $c, Book $book): array
    {
        return [
            'hub_id' => $c->id,
            'slug' => $c->slug,
            'sequence' => (int) $c->sequence,
            'number' => $c->number,
            'section' => $c->section,
            'title' => $c->title,
            'subtitle' => $c->subtitle,
            'title_en' => $c->title_en,
            'language' => $c->language ?: $book->language,
            'first_page' => (int) $c->first_page,
            'last_page' => (int) $c->last_page,
            'publication_date' => ($c->publication_date ?? $book->publication_date)?->format('Y-m-d'),
            'doi' => $book->doi_prefix.'/'.$c->doi_suffix,
            'abstract' => $c->abstract_approved,
            'keywords' => self::strings($c->keywords),
            'references' => self::references($c->references),
            'contributors' => $c->contributions->map(fn (Contribution $x) => self::contributor($x))->values()->all(),
            'documents' => self::documents($c->relationLoaded('documents') ? $c->documents : $c->documents()->get()),
            'status' => $c->status,
            'doi_status' => $c->doi_status,
        ];
    }

    public static function contributor(Contribution $c): array
    {
        return [
            'hub_person_id' => $c->person_id,
            'name' => $c->person?->name,
            'sort_name' => $c->person?->sort_name,
            'orcid' => $c->person?->orcid,
            'role' => $c->role,
            'sequence' => (int) $c->sequence,
            'affiliation' => $c->affiliation(),
        ];
    }

    /** Sumário: seções na ordem em que aparecem, cada uma com os ids dos capítulos. */
    public static function toc(Book $book): array
    {
        $toc = [];

        foreach ($book->chapters as $chapter) {
            $section = $chapter->section;
            $key = (string) $section;

            if (! isset($toc[$key])) {
                $toc[$key] = ['section' => $section, 'chapters' => []];
            }
            $toc[$key]['chapters'][] = $chapter->id;
        }

        return array_values($toc);
    }

    public static function commercial(CommercialLink $l): array
    {
        return [
            'modality' => $l->modality,
            'label' => $l->label,
            'woo_product_id' => $l->woo_product_id,
            'url' => $l->url,
            'enabled' => (bool) $l->enabled,
            'sort_order' => (int) $l->sort_order,
        ];
    }

    public static function documentStub(string $type): array
    {
        return ['type' => $type, 'attachment_id' => null, 'url' => null, 'version' => null, 'generated_at' => null];
    }

    /**
     * Só as fichas que o site já recebeu (com anexo): o WP substitui a lista a
     * cada PUT, então mandar as publicadas é o que mantém o PDF na página.
     *
     * @param  iterable<Document>  $documents
     */
    public static function documents(iterable $documents): array
    {
        return collect($documents)
            ->filter(fn (Document $d) => $d->isPublished())
            ->map(fn (Document $d) => [
                'type' => $d->type,
                'attachment_id' => $d->wp_attachment_id,
                'url' => $d->public_url,
                'version' => $d->template_version,
                'generated_at' => $d->generated_at?->toIso8601String(),
            ])->values()->all();
    }

    private static function strings(?array $values): array
    {
        return array_values(array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : $v, $values ?? []), fn ($v) => filled($v)));
    }

    /** `[{text, doi}]`, sempre com as duas chaves. */
    private static function references(?array $refs): array
    {
        return collect($refs ?? [])
            ->map(fn ($r) => is_string($r) ? ['text' => $r, 'doi' => null] : ['text' => $r['text'] ?? '', 'doi' => $r['doi'] ?? null])
            ->filter(fn ($r) => filled($r['text']))
            ->values()->all();
    }
}
