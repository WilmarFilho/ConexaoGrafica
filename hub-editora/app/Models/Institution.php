<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Instituição de afiliação (universidade, hospital, sociedade...). */
class Institution extends Model
{
    protected $guarded = [];

    protected $attributes = ['country' => 'BR'];

    protected static function booted(): void
    {
        static::saving(function (Institution $i) {
            $i->country = strtoupper(trim((string) $i->country)) ?: 'BR';
            $i->acronym = filled($i->acronym) ? trim((string) $i->acronym) : null;
        });
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    /** "Universidade Federal de Goiás (UFG) · Goiânia/GO" */
    public function getLabelAttribute(): string
    {
        $place = trim(implode('/', array_filter([$this->city, $this->state])));

        return $this->name.($this->acronym ? " ({$this->acronym})" : '').($place ? " · {$place}" : '');
    }

    /** Afiliação no formato do payload do WordPress. */
    public function toAffiliation(): array
    {
        return ['name' => $this->name, 'city' => $this->city, 'state' => $this->state, 'country' => $this->country];
    }
}
