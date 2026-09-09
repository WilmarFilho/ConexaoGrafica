<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Order extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'status_manual' => 'boolean',
            'requires_shipping' => 'boolean',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'raw' => 'array',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class)->latestOfMany();
    }

    public function syncLogs(): MorphMany
    {
        return $this->morphMany(SyncLog::class, 'subject');
    }

    // ---- Escopos usados pelas telas -----------------------------------

    public function scopeAwaitingShipment(Builder $query): Builder
    {
        return $query
            ->where('requires_shipping', true)
            ->whereIn('status', OrderStatus::awaitingShipment());
    }

    public function scopeFromChannel(Builder $query, string $slug): Builder
    {
        return $query->whereHas('channel', fn (Builder $q) => $q->where('slug', $slug));
    }

    // ---- Apresentação -------------------------------------------------

    public function getTotalFormattedAttribute(): string
    {
        return 'R$ ' . number_format($this->total_cents / 100, 2, ',', '.');
    }

    /**
     * De qual loja/landing o pedido veio dentro do canal (ex.: Pagar.me →
     * "ovacuodopoder"). As landings mandam isso em metadata.store.
     */
    public function getSourceLabelAttribute(): ?string
    {
        $store = $this->raw['metadata']['store'] ?? $this->raw['metadata']['origin'] ?? null;

        return is_string($store) && $store !== '' ? $store : null;
    }

    /** Endereço em uma linha, para tabelas. */
    public function getShipCityStateAttribute(): ?string
    {
        if (! $this->ship_city) {
            return null;
        }

        return trim($this->ship_city . ' · ' . $this->ship_state, ' ·');
    }

    /** Resumo "2× Título · 1× Outro" para a listagem. */
    public function getItemsSummaryAttribute(): string
    {
        return $this->items
            ->map(fn (OrderItem $i) => $i->quantity . '× ' . $i->name)
            ->implode(' · ');
    }
}
