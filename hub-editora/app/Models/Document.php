<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * Ficha científica em PDF de um livro (`book_sheet`) ou capítulo
 * (`chapter_sheet`). Uma por alvo e tipo. `hash` resume os dados usados na
 * geração (mais a versão do template): só se gera de novo quando ele muda.
 * `stale` diz que o site não tem esta versão (nunca enviada ou desatualizada).
 */
class Document extends Model
{
    protected $guarded = [];

    public const BOOK_SHEET = 'book_sheet';

    public const CHAPTER_SHEET = 'chapter_sheet';

    public const TYPES = [
        self::BOOK_SHEET => 'Ficha do livro',
        self::CHAPTER_SHEET => 'Ficha do capítulo',
    ];

    public const DISK = 'local';

    protected $attributes = ['stale' => true];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'stale' => 'boolean',
            'wp_attachment_id' => 'integer',
        ];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function isPublished(): bool
    {
        return $this->wp_attachment_id !== null && filled($this->public_url);
    }

    public function fileExists(): bool
    {
        return filled($this->path) && Storage::disk(self::DISK)->exists($this->path);
    }

    public function contents(): string
    {
        return (string) Storage::disk(self::DISK)->get($this->path);
    }

    public function absolutePath(): string
    {
        return Storage::disk(self::DISK)->path($this->path);
    }

    /** Nome do arquivo para download (o mesmo gravado no disco). */
    public function filename(): string
    {
        return basename((string) $this->path);
    }

    /** Situação para a tela: `none` (nunca gerada), `unpublished`, `stale` ou `published`. */
    public function state(): string
    {
        if (! $this->exists) {
            return 'none';
        }

        if (! $this->isPublished()) {
            return 'unpublished';
        }

        return $this->stale ? 'stale' : 'published';
    }

    public static function stateLabel(string $state): string
    {
        return match ($state) {
            'published' => 'Ficha publicada no site',
            'stale' => 'Ficha desatualizada no site',
            'unpublished' => 'Ficha gerada, ainda não publicada',
            default => 'Ficha ainda não gerada',
        };
    }

    /** Marca como desatualizadas as fichas de um livro ou capítulo (os dados públicos mudaram). */
    public static function markStale(Model $target): int
    {
        return static::query()
            ->where('documentable_type', $target->getMorphClass())
            ->where('documentable_id', $target->getKey())
            ->where('stale', false)
            ->update(['stale' => true]);
    }

    /** @param Builder<Document> $query */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('wp_attachment_id');
    }
}
