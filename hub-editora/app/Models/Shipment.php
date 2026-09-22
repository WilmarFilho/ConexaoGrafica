<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    public const QUOTED = 'quoted';
    public const PURCHASED = 'purchased';
    public const LABEL_GENERATED = 'label_generated';
    public const SHIPPED = 'shipped';
    public const DELIVERED = 'delivered';
    public const PROBLEM = 'problem';
    public const CANCELLED = 'cancelled';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'channel_notified' => 'boolean',
            'label_generated_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'raw' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Página pública de rastreio. Melhor Rastreio abre sem captcha e cobre
     * Correios, Jadlog e as demais transportadoras do Melhor Envio.
     */
    public static function trackingUrl(?string $code): ?string
    {
        $code = trim((string) $code);

        return preg_match('/^[A-Za-z0-9-]{6,40}$/', $code)
            ? 'https://www.melhorrastreio.com.br/rastreio/'.rawurlencode(strtoupper($code))
            : null;
    }
}
