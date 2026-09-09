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

    /** Direções: in = canal → hub, out = hub → canal, manual = pessoa no painel. */
    public const IN = 'in';
    public const OUT = 'out';
    public const MANUAL = 'manual';

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** Rótulo legível da ação, para a tela de auditoria. */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'order.imported' => 'Pedido importado',
            'order.updated' => 'Pedido atualizado pelo canal',
            'order.removed_duplicate' => 'Duplicado removido',
            'orders.sync' => 'Varredura de pedidos',
            'products.sync' => 'Sincronização de catálogo',
            'webhook.failed' => 'Webhook falhou',
            'status.changed' => 'Etapa alterada manualmente',
            'status.unlocked' => 'Etapa liberada para o canal',
            'label.generated' => 'Etiqueta gerada',
            'label.failed' => 'Etiqueta falhou',
            'tracking.sent' => 'Rastreio enviado ao canal',
            'tracking.failed' => 'Envio de rastreio falhou',
            'settings.changed' => 'Integração alterada',
            'settings.tested' => 'Integração testada',
            default => $this->action,
        };
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
            'user_id' => auth()->id(),
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
