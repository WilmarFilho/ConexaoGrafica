<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Um arquivo XML (doi_batch) enviado à Crossref. Os DOIs do arquivo ficam em
 * `items`. HTTP 200 no envio leva só a `sent`; `done`/`failed` vêm do relatório
 * de processamento (ver App\Crossref\DepositService::refresh).
 */
class Deposit extends Model
{
    protected $guarded = [];

    public const QUEUED = 'queued';

    public const SENT = 'sent';

    public const PROCESSING = 'processing';

    public const DONE = 'done';

    public const FAILED = 'failed';

    public const TEST = 'test';

    public const PRODUCTION = 'production';

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'result_at' => 'datetime',
            'http_status' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(DepositItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function getEnvironmentLabelAttribute(): string
    {
        return $this->environment === self::PRODUCTION ? 'Produção' : 'Homologação';
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            self::QUEUED => 'gray',
            self::SENT, self::PROCESSING => 'warning',
            self::DONE => 'success',
            self::FAILED => 'danger',
            default => 'gray',
        };
    }

    /** Nome do arquivo como enviado à Crossref (fname) e usado na consulta do resultado. */
    public function filename(): string
    {
        return $this->batch_id.'.xml';
    }

    /** Depósito já saiu daqui e ainda não tem relatório final. */
    public function awaitingResult(): bool
    {
        return in_array($this->status, [self::SENT, self::PROCESSING], true);
    }

    /**
     * Pode ser (re)enviado: nunca saiu, ou o envio falhou antes de haver relatório.
     * Um depósito que falhou no relatório não se reenvia: gera-se um depósito novo.
     */
    public function canBeSent(): bool
    {
        return $this->status === self::QUEUED
            || ($this->status === self::FAILED && $this->result_at === null);
    }

    /** Depósitos com resultado a consultar (Deposit::pendingResult()->get()). */
    public function scopePendingResult($query)
    {
        return $query->whereIn('status', [self::SENT, self::PROCESSING]);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::QUEUED => 'Na fila',
            self::SENT => 'Enviado',
            self::PROCESSING => 'Em processamento',
            self::DONE => 'Concluído',
            self::FAILED => 'Falhou',
            default => $this->status,
        };
    }
}
