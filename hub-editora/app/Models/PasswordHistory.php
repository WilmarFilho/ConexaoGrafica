<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Hash de uma senha anterior do usuário (para não repetir as últimas). */
class PasswordHistory extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $hidden = ['password'];
}
