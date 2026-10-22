<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Pessoa × obra (livro ou capítulo), com papel e ordem. Organizador é o
 * papel `editor` da Crossref. A afiliação é a instituição cadastrada ou,
 * na falta dela, um texto livre.
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
