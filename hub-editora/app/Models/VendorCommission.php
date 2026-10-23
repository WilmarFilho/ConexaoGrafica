<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comissão de um vendedor sobre um produto num pedido (uma linha por pedido +
 * produto; os itens do pedido são recriados a cada importação, por isso a
 * chave não é o item). Estados:
 *
 *   pending    pedido ainda sem pagamento confirmado
 *   available  pedido pago: entra no próximo repasse
 *   paid       já repassado (não muda mais)
 *   cancelled  pedido cancelado/estornado antes do repasse
 *
 * Pedido cancelado depois do repasse gera um estorno (`kind = reversal`,
 * valor negativo, disponível) que desconta no repasse seguinte.
 */
class VendorCommission extends Model
{
    protected $guarded = [];

    public const PENDING = 'pending';

    public const AVAILABLE = 'available';

    public const PAID = 'paid';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [
        self::PENDING => 'Aguardando pagamento',
        self::AVAILABLE => 'A repassar',
        self::PAID => 'Repassada',
        self::CANCELLED => 'Cancelada',
    ];

    public const SALE = 'sale';

    public const REVERSAL = 'reversal';

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'earned_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(VendorPayout::class, 'payout_id');
    }

    /** Já está num repasse (rascunho ou pago): o valor não muda mais. */
    public function isLocked(): bool
    {
        return $this->status === self::PAID || $this->payout_id !== null;
    }

    public function statusLabel(): string
    {
        if ($this->status === self::AVAILABLE && $this->payout_id) {
            return 'Em repasse';
        }

        return self::STATUSES[$this->status] ?? $this->status;
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            self::PENDING => 'gray',
            self::AVAILABLE => 'warning',
            self::PAID => 'success',
            self::CANCELLED => 'danger',
            default => 'gray',
        };
    }

    /** @param Builder<VendorCommission> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::AVAILABLE)->whereNull('payout_id');
    }

    /** Fusão de produtos duplicados: as comissões passam para o produto que fica. */
    public static function moveProduct(int $from, int $to): void
    {
        foreach (self::query()->where('product_id', $from)->get() as $commission) {
            $taken = self::query()->where('order_id', $commission->order_id)->where('product_id', $to)->where('kind', $commission->kind)->exists();
            if (! $taken) {
                $commission->update(['product_id' => $to]);
            }
        }
    }
}
