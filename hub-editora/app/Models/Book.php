<?php

namespace App\Models;

use App\Editorial\DoiSuffix;
use App\Editorial\EditorialException;
use App\Editorial\Keywords;
use App\Editorial\Slug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;

/**
 * Livro do módulo Editorial: a obra que recebe DOI e vira página pública
 * no WordPress. O Hub é a fonte de verdade; o produto do catálogo
 * (`product_id`) é só o vínculo com a loja.
 */
class Book extends Model
{
    protected $guarded = [];

    public const TYPES = [
        'edited_book' => 'Livro organizado',
        'monograph' => 'Monografia (autoral)',
        'reference' => 'Obra de referência',
        'other' => 'Outro',
    ];

    public const FORMATS = [
        'print' => 'Impresso',
        'ebook' => 'E-book',
        'both' => 'Impresso e e-book',
    ];

    public const STATUSES = [
        'draft' => 'Rascunho',
        'review' => 'Em revisão',
        'approved' => 'Aprovado',
        'published' => 'Publicado',
        'unpublished' => 'Fora de catálogo',
    ];

    /** Mesmo enum para o livro e cada capítulo. */
    public const DOI_STATUSES = [
        'none' => 'Sem DOI',
        'validated' => 'Validado',
        'ready' => 'Pronto',
        'sent' => 'Enviado',
        'processing' => 'Processando',
        'registered' => 'Registrado',
        'failed' => 'Falhou',
    ];

    /** doi_status >= sent: slug e sufixo não mudam mais. */
    public const DOI_LOCKED = ['sent', 'processing', 'registered'];

    /** Estados em que o livro pode ir (ou voltar) para o site. */
    public const PUBLISHABLE = ['approved', 'published', 'unpublished'];

    public const LANGUAGES = [
        'pt' => 'Português',
        'en' => 'Inglês',
        'es' => 'Espanhol',
        'fr' => 'Francês',
        'it' => 'Italiano',
        'de' => 'Alemão',
    ];

    protected $attributes = [
        'alt_title_lang' => 'en',
        'type' => 'edited_book',
        'language' => 'pt',
        'publisher' => 'Conexão Editora',
        'publication_place' => 'Goiânia',
        'format' => 'both',
        'status' => 'draft',
        'doi_status' => 'none',
    ];

    protected function casts(): array
    {
        return [
            'extra_languages' => 'array',
            'other_isbns' => 'array',
            'keywords' => 'array',
            'keywords_en' => 'array',
            'references' => 'array',
            'publication_year' => 'integer',
            'edition_number' => 'integer',
            'pages' => 'integer',
            'publication_date' => 'date',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'wp_post_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Book $book) {
            $book->keywords = Keywords::normalize($book->keywords);
            $book->keywords_en = Keywords::normalize($book->keywords_en);
            $book->doi_prefix = filled($book->doi_prefix) ? trim((string) $book->doi_prefix) : DoiSuffix::prefix();
            $book->doi_suffix = strtolower(trim((string) $book->doi_suffix));

            if ($book->doi_suffix === '') {
                $book->doi_suffix = DoiSuffix::forBook((int) ($book->publication_year ?: date('Y')), $book->doi_prefix);
            }

            // Sufixo e prefixo só mudam enquanto o DOI não foi validado (contrato, seção 1).
            if ($book->exists && ($book->isDirty('doi_suffix') || $book->isDirty('doi_prefix')) && $book->getOriginal('doi_status') !== 'none') {
                throw new EditorialException('O DOI não pode mudar depois de validado.');
            }

            Slug::apply($book, 'title');
        });
    }

    // ---- Relações ----------------------------------------------------------

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('sequence');
    }

    public function contributions(): MorphMany
    {
        return $this->morphMany(Contribution::class, 'contributable')->orderBy('sequence');
    }

    public function commercialLinks(): HasMany
    {
        return $this->hasMany(CommercialLink::class)->orderBy('sort_order');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Depósitos na Crossref (frente D). O mais recente é o que manda no doi_status. */
    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    /** Fichas PDF (frente E). */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /** A ficha científica do livro, quando já gerada. */
    public function sheet(): MorphOne
    {
        return $this->morphOne(Document::class, 'documentable')->where('type', Document::BOOK_SHEET);
    }

    // ---- Atributos derivados ----------------------------------------------

    /** `{doi_prefix}/{doi_suffix}` — único. */
    public function getDoiAttribute(): string
    {
        return "{$this->doi_prefix}/{$this->doi_suffix}";
    }

    public function getDoiUrlAttribute(): string
    {
        return 'https://doi.org/'.$this->doi;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function doiStatusLabel(): string
    {
        return self::DOI_STATUSES[$this->doi_status] ?? $this->doi_status;
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            'draft' => 'gray',
            'review' => 'warning',
            'approved' => 'info',
            'published' => 'success',
            'unpublished' => 'danger',
            default => 'gray',
        };
    }

    public static function doiStatusColor(?string $status): string
    {
        return match ($status) {
            'none' => 'gray',
            'validated', 'ready' => 'info',
            'sent', 'processing' => 'warning',
            'registered' => 'success',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    /** Sufixo editável: só em rascunho e antes da validação do XML. */
    public function isDoiEditable(): bool
    {
        return $this->status === 'draft' && $this->doi_status === 'none';
    }

    public function isDoiLocked(): bool
    {
        return in_array($this->doi_status, self::DOI_LOCKED, true);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function canBePublished(): bool
    {
        return in_array($this->status, self::PUBLISHABLE, true);
    }

    /** Pode ser depositado na Crossref (contrato, seção 3): publicado e com URL. */
    public function canBeDeposited(): bool
    {
        return $this->status === 'published' && filled($this->landing_url);
    }

    /** Organizadores (ou autores, numa monografia), em ordem. */
    public function mainContributors(): Collection
    {
        $role = $this->type === 'edited_book' ? 'editor' : 'author';
        $main = $this->contributions->where('role', $role);

        return ($main->isNotEmpty() ? $main : $this->contributions)->values();
    }

    public function contributorsLine(): string
    {
        return $this->mainContributors()->map(fn (Contribution $c) => $c->person?->name)->filter()->implode('; ');
    }

    /** Todos os ISBNs informados (impresso, e-book, outros), só dígitos. */
    public function allIsbns(): array
    {
        $others = collect($this->other_isbns ?? [])->pluck('value')->all();

        return array_values(array_filter([$this->isbn_print, $this->isbn_ebook, ...$others]));
    }

    /** @param Builder<Book> $query */
    public function scopeStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }
}
