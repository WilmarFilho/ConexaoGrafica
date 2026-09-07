<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SyncLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** Atalho: SyncLog::record($channel, 'in', 'order.imported', $order, 'msg', [...]) */
    public static function record(
        ?Channel $channel,
        string $direction,
        string $action,
        ?Model $subject = null,
        ?string $message = null,
        array $context = [],
        string $level = 'info',
    ): self {
        return static::create([
            'channel_id' => $channel?->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'direction' => $direction,
            'action' => $action,
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ]);
    }
}
