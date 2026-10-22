<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Pessoa × obra (livro ou capítulo), com papel e ordem. Organizador é o
 * papel `editor` da Crossref. A afiliação é a instituição cadastrada ou,
 * na falta dela, um texto livre.
 *
 * A instituição informada para a pessoa no livro vale para os capítulos dela no
 * mesmo livro que estiverem sem afiliação (num livro de 70 autores, ninguém repete
 * a mesma instituição capítulo por capítulo).
 */
class Contribution extends Model
{
    protected $guarded = [];

    public const ROLES = [
        'author' => 'Autor',
        'editor' => 'Organizador',
        'translator' => 'Tradutor',
        'reviewer' => 'Revisor',
        'other' => 'Outro',
    ];

    protected function casts(): array
    {
        return ['sequence' => 'integer'];
    }

    protected static function booted(): void
    {
        // Autor de capítulo sem afiliação herda a que a pessoa tem no livro.
        static::saving(function (Contribution $c) {
            if ($c->contributable_type !== 'chapter' || $c->hasAffiliation()) {
                return;
            }
            $bookId = Chapter::query()->whereKey($c->contributable_id)->value('book_id');
            $fromBook = $bookId ? static::query()
                ->where('contributable_type', 'book')->where('contributable_id', $bookId)
                ->where('person_id', $c->person_id)
                ->where(fn ($q) => $q->whereNotNull('institution_id')->orWhere('affiliation_text', '<>', ''))
                ->first() : null;
            if ($fromBook) {
                $c->institution_id = $fromBook->institution_id;
                $c->affiliation_text = $fromBook->affiliation_text;
            }
        });

        // Afiliação informada no livro completa os capítulos da pessoa que estão sem.
        static::saved(function (Contribution $c) {
            if ($c->contributable_type !== 'book' || ! $c->hasAffiliation()) {
                return;
            }
            $chapterIds = Chapter::query()->where('book_id', $c->contributable_id)->pluck('id');
            static::query()
                ->where('contributable_type', 'chapter')->whereIn('contributable_id', $chapterIds)
                ->where('person_id', $c->person_id)
                ->whereNull('institution_id')
                ->where(fn ($q) => $q->whereNull('affiliation_text')->orWhere('affiliation_text', ''))
                ->update(['institution_id' => $c->institution_id, 'affiliation_text' => $c->affiliation_text]);
        });
    }

    public function hasAffiliation(): bool
    {
        return $this->institution_id !== null || filled($this->affiliation_text);
    }

    public function contributable(): MorphTo
    {
        return $this->morphTo();
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    /** Afiliação no formato do payload: instituição cadastrada, texto livre ou nada. */
    public function affiliation(): ?array
    {
        if ($this->institution) {
            return $this->institution->toAffiliation();
        }

        if (filled($this->affiliation_text)) {
            return ['name' => $this->affiliation_text, 'city' => null, 'state' => null, 'country' => null];
        }

        return null;
    }

    /** Regra do Romário: para ser publicada, a pessoa precisa de ORCID ou instituição. */
    public function isPublishable(): bool
    {
        return filled($this->person?->orcid) || $this->institution_id !== null || filled($this->affiliation_text);
    }
}
