<?php

namespace App\Models;

use App\Editorial\DoiSuffix;
use App\Editorial\EditorialException;
use App\Editorial\Keywords;
use App\Editorial\Slug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * Capítulo: pertence a exatamente um livro e sempre recebe DOI
 * (`{sufixo do livro}.{NN}`). O resumo publicado é sempre `abstract_approved`.
 */
class Chapter extends Model
{
    protected $guarded = [];

    public const STATUSES = [
        'draft' => 'Rascunho',
        'review' => 'Em revisão',
        'approved' => 'Aprovado',
    ];

    protected $attributes = [
        'status' => 'draft',
        'doi_status' => 'none',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'first_page' => 'integer',
            'last_page' => 'integer',
            'keywords' => 'array',
            'references' => 'array',
            'ai_evidence' => 'array',
            'publication_date' => 'date',
            'approved_at' => 'datetime',
            'wp_post_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Chapter $chapter) {
            $chapter->keywords = Keywords::normalize($chapter->keywords);
            $book = $chapter->book ?? Book::find($chapter->book_id);

            if (! $chapter->sequence) {
                $chapter->sequence = ((int) Chapter::query()->where('book_id', $chapter->book_id)->max('sequence')) + 1;
            }

            $chapter->doi_suffix = strtolower(trim((string) $chapter->doi_suffix));
            if ($chapter->doi_suffix === '' && $book) {
                $chapter->doi_suffix = DoiSuffix::forChapter($book, (int) $chapter->sequence);
            }

            if ($chapter->exists && $chapter->isDirty('doi_suffix') && $chapter->getOriginal('doi_status') !== 'none') {
                throw new EditorialException('O DOI do capítulo não pode mudar depois de validado.');
            }

            if ($chapter->first_page && $chapter->last_page && $chapter->last_page < $chapter->first_page) {
                throw new EditorialException('A última página do capítulo não pode vir antes da primeira.');
            }

            Slug::apply($chapter, 'title', fn ($q) => $q->where('book_id', $chapter->book_id));
        });
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function contributions(): MorphMany
    {
        return $this->morphMany(Contribution::class, 'contributable')->orderBy('sequence');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Fichas PDF (frente E). */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /** A ficha científica do capítulo, quando já gerada. */
    public function sheet(): MorphOne
    {
        return $this->morphOne(Document::class, 'documentable')->where('type', Document::CHAPTER_SHEET);
    }

    /** `{prefixo do livro}/{sufixo do capítulo}`. */
    public function getDoiAttribute(): string
    {
        return ($this->book?->doi_prefix ?? DoiSuffix::prefix()).'/'.$this->doi_suffix;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            'draft' => 'gray',
            'review' => 'warning',
            'approved' => 'success',
            default => 'gray',
        };
    }

    public function isDoiEditable(): bool
    {
        return $this->doi_status === 'none';
    }

    /** Idioma do capítulo ou, por herança, do livro. */
    public function effectiveLanguage(): ?string
    {
        return $this->language ?: $this->book?->language;
    }

    public function effectivePublicationDate(): ?Carbon
    {
        return $this->publication_date ?? $this->book?->publication_date;
    }

    /** "21–34" */
    public function pagesLabel(): string
    {
        return $this->first_page.'–'.$this->last_page;
    }

    public function authorsLine(): string
    {
        return $this->contributions->map(fn (Contribution $c) => $c->person?->name)->filter()->implode('; ');
    }

    /** O resumo que seria publicado hoje: aprovado, senão o original, senão a sugestão da IA. */
    public function bestAbstract(): ?string
    {
        return $this->abstract_approved ?: ($this->abstract_original ?: $this->abstract_ai);
    }
}
