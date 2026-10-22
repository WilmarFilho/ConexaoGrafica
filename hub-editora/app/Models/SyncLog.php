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
            'alert.sent' => 'Aviso de queda enviado',
            'sync.manual' => 'Sincronização manual',
            'auth.login' => 'Login',
            'auth.logout' => 'Saída',
            'auth.failed' => 'Login falhou',
            'auth.locked' => 'Conta bloqueada',
            'user.unlocked' => 'Conta desbloqueada',
            'user.disabled' => 'Usuário desativado',
            'user.enabled' => 'Usuário reativado',
            'user.role_changed' => 'Perfil alterado',
            'access.review.sent' => 'Revisão de acessos enviada',
            'access.review.confirmed' => 'Revisão de acessos confirmada',
            'security.scan' => 'Varredura de vulnerabilidades',
            'backup.sent' => 'Backup externo enviado',
            'backup.failed' => 'Backup externo falhou',
            'backup.checked' => 'Backup externo conferido',
            'db.encrypted' => 'Tabelas cifradas',
            'pii.purged' => 'Dados pessoais apagados',
            'security.digest' => 'Resumo de segurança enviado',
            'store.remapped' => 'Loja mudou de endereço',
            // Conexão DOI, frente C: importação de PDF com IA
            'import.received' => 'PDF recebido para importação',
            'import.extracted' => 'Texto do PDF extraído',
            'import.front_matter' => 'Dados da obra lidos pela IA',
            'import.segmented' => 'Capítulos segmentados',
            'import.chapter' => 'Capítulo analisado pela IA',
            'import.suggested' => 'Proposta da IA pronta',
            'import.failed' => 'Importação falhou',
            'import.reprocess' => 'Importação reenviada',

            // ---- Módulo Editorial (Conexão DOI) ----
            'editorial.book_created' => 'Livro cadastrado',
            'editorial.review_requested' => 'Livro enviado para revisão',
            'editorial.approved' => 'Livro aprovado',
            'editorial.returned' => 'Livro devolvido ao rascunho',
            'editorial.chapter_review_requested' => 'Capítulo enviado para revisão',
            'editorial.chapter_approved' => 'Capítulo aprovado',
            'editorial.chapters_imported' => 'Capítulos importados por JSON',
            'editorial.chapters_renumbered' => 'Sufixos DOI dos capítulos renumerados',
            'editorial.publish_requested' => 'Publicação no site solicitada',
            'editorial.unpublish_requested' => 'Retirada de catálogo solicitada',
            'publish.sent' => 'Livro publicado no site',
            'publish.unpublished' => 'Livro marcado fora de catálogo no site',
            'publish.failed' => 'Publicação no site falhou',
            // Conexão DOI: fichas PDF (frente E)
            'document.generated' => 'Ficha PDF gerada',
            'document.published' => 'Fichas PDF enviadas ao site',
            'document.requested' => 'Regeneração das fichas solicitada',
            'document.failed' => 'Fichas PDF falharam',

            // Conexão DOI: depósitos na Crossref (App\Crossref)
            'deposit.created' => 'Depósito Crossref gerado',
            'deposit.sent' => 'Depósito enviado à Crossref',
            'deposit.pending' => 'Depósito Crossref em processamento',
            'deposit.done' => 'Depósito Crossref concluído',
            'deposit.failed' => 'Depósito Crossref falhou',
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
