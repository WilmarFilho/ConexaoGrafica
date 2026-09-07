<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    public const WOOCOMMERCE = 'woocommerce';
    public const PAGARME = 'pagarme';
    public const AMAZON = 'amazon';
    public const BLING = 'bling';
    public const MELHOR_ENVIO = 'melhor_envio';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'settings' => 'array',
            'last_sync_at' => 'datetime',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public static function bySlug(string $slug): self
    {
        return static::where('slug', $slug)->firstOrFail();
    }
}
