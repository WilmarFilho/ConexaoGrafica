<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'physical' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function channelRefs(): HasMany
    {
        return $this->hasMany(ProductChannelRef::class);
    }

    /** Sem peso e dimensões não dá para cotar frete: a Central de Expedição sinaliza. */
    public function hasShippingDimensions(): bool
    {
        return $this->weight_grams && $this->width_cm && $this->height_cm && $this->depth_cm;
    }
}
