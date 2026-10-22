<?php

namespace App\Editorial;

use App\Models\Book;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Slug das páginas públicas (/livro/{slug}/ e /livro/{slug}/capitulo/{nn}/).
 * Gerado do título na primeira gravação, editável enquanto o DOI não foi
 * enviado à Crossref e imutável depois (doi_status >= sent): a URL registrada
 * não pode mudar.
 */
class Slug
{
    public static function make(?string $text): string
    {
        return Str::limit(Str::slug((string) $text), 180, '') ?: 'sem-titulo';
    }

    /** DOI já enviado (sent, processing ou registered): slug travado. */
    public static function isLocked(Model $model): bool
    {
        $status = $model->exists ? $model->getOriginal('doi_status') : $model->doi_status;

        return in_array($status, Book::DOI_LOCKED, true);
    }

    /**
     * Para chamar no `saving`: preenche a partir do título, normaliza, impede a
     * troca depois do envio e garante unicidade (dentro do escopo, se houver).
     */
    public static function apply(Model $model, string $sourceAttribute = 'title', ?callable $scope = null): void
    {
        if ($model->exists && $model->isDirty('slug') && self::isLocked($model)) {
            throw new EditorialException('O slug não pode mudar depois que o DOI foi enviado à Crossref.');
        }

        $model->slug = self::make(filled($model->slug) ? $model->slug : $model->{$sourceAttribute});
        $model->slug = self::unique($model, $scope);
    }

    private static function unique(Model $model, ?callable $scope): string
    {
        $base = $model->slug;
        $slug = $base;
        $n = 2;

        $taken = function (string $candidate) use ($model, $scope): bool {
            /** @var Builder $q */
            $q = $model->newQuery()->where('slug', $candidate);
            if ($model->exists) {
                $q->whereKeyNot($model->getKey());
            }
            if ($scope) {
                $scope($q);
            }

            return $q->exists();
        };

        while ($taken($slug)) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }
}
