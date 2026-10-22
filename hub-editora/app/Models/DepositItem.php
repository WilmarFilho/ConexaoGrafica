<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um DOI dentro de um depósito (o livro ou um capítulo). `registered` só é
 * gravado a partir do relatório de processamento da Crossref, nunca pelo HTTP 200.
 */
class DepositItem extends Model
{
    protected $guarded = [];

    public const PENDING = 'pending';

    public const REGISTERED = 'registered';

    public const FAILED = 'failed';

    public const BOOK = 'book';

    public const CHAPTER = 'chapter';

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(Deposit::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::PENDING => 'Aguardando',
            self::REGISTERED => 'Registrado',
            self::FAILED => 'Falhou',
            default => $this->status,
        };
    }
}
