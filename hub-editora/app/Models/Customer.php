<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $guarded = [];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Encontra ou cria pelo e-mail (ou documento, quando o canal não manda
     * e-mail, como acontece na Amazon).
     */
    public static function findOrCreateFrom(array $data): self
    {
        $query = static::query();

        if (! empty($data['email'])) {
            $query->where('email', mb_strtolower($data['email']));
        } elseif (! empty($data['document'])) {
            $query->where('document', preg_replace('/\D/', '', $data['document']));
        } else {
            return static::create($data);
        }

        return $query->firstOr(fn () => static::create([
            ...$data,
            'email' => isset($data['email']) ? mb_strtolower($data['email']) : null,
            'document' => isset($data['document']) ? preg_replace('/\D/', '', $data['document']) : null,
        ]));
    }
}
