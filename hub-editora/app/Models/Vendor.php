<?php

namespace App\Models;

use App\Vendors\Commissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Vendedor do multivendor: autor ou parceiro que vende livros pela editora.
 * Dono de produtos do catálogo; recebe `commission_pct` do valor dos itens
 * vendidos (o produto pode sobrepor com `vendor_commission_pct`).
 */
class Vendor extends Model
{
    protected $guarded = [];

    public const PAYOUT_METHODS = [
        'pix' => 'PIX',
        'bank' => 'Transferência bancária',
    ];

    /** Padrão do MVX na loja antiga: 65% do valor do item para o vendedor. */
    public const DEFAULT_COMMISSION_PCT = 65;

    protected $attributes = [
        'commission_pct' => self::DEFAULT_COMMISSION_PCT,
        'active' => true,
    ];

    protected function casts(): array
    {
        return [
            'commission_pct' => 'decimal:2',
            'payout_details' => 'encrypted',
            'commissions_start_at' => 'date',
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Vendor $vendor) {
            $vendor->document = $vendor->document ? (preg_replace('/\D/', '', $vendor->document) ?: null) : null;
        });

        static::updated(function (Vendor $vendor) {
            if ($vendor->wasChanged(['commission_pct', 'commissions_start_at'])) {
                Commissions::make()->syncVendor($vendor);
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(VendorCommission::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(VendorPayout::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @param Builder<Vendor> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /** Saldo a repassar: comissões disponíveis ainda fora de repasse (estornos descontam). */
    public function availableCents(): int
    {
        return (int) $this->commissions()->where('status', VendorCommission::AVAILABLE)->whereNull('payout_id')->sum('amount_cents');
    }

    /** Vendas ainda sem pagamento confirmado pelo cliente. */
    public function pendingCents(): int
    {
        return (int) $this->commissions()->where('status', VendorCommission::PENDING)->sum('amount_cents');
    }

    public function paidCents(): int
    {
        return (int) $this->commissions()->where('status', VendorCommission::PAID)->sum('amount_cents');
    }

    /** "123.456.789-01" / "12.345.678/0001-90" para exibir. */
    public function getDocumentLabelAttribute(): ?string
    {
        $d = (string) $this->document;

        return match (strlen($d)) {
            11 => vsprintf('%s%s%s.%s%s%s.%s%s%s-%s%s', str_split($d)),
            14 => vsprintf('%s%s.%s%s%s.%s%s%s/%s%s%s%s-%s%s', str_split($d)),
            0 => null,
            default => $d,
        };
    }

    public static function money(int $cents): string
    {
        return ($cents < 0 ? '- ' : '').'R$ '.number_format(abs($cents) / 100, 2, ',', '.');
    }
}
