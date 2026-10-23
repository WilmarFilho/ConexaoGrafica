<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Repasse a um vendedor: junta as comissões disponíveis até `period_until`.
 * Nasce como rascunho (as comissões ficam presas a ele); vira pago quando a
 * editora registra a transferência, ou é cancelado e as comissões voltam.
 */
class VendorPayout extends Model
{
    protected $guarded = [];

    public const DRAFT = 'draft';

    public const PAID = 'paid';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [
        self::DRAFT => 'A pagar',
        self::PAID => 'Pago',
        self::CANCELLED => 'Cancelado',
    ];

    public const METHODS = [
        'pix' => 'PIX',
        'bank' => 'Transferência bancária',
        'other' => 'Outro',
    ];

    protected function casts(): array
    {
        return [
            'period_until' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(VendorCommission::class, 'payout_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            self::DRAFT => 'warning',
            self::PAID => 'success',
            self::CANCELLED => 'gray',
            default => 'gray',
        };
    }

    public function getAmountFormattedAttribute(): string
    {
        return Vendor::money((int) $this->amount_cents);
    }
}
