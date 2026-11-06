<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Importação de um livro em PDF com IA (Conexão DOI, frente C).
 *
 * Estados (contrato, seção 3):
 *   received → extracting → segmenting → suggested → review → approved → integrated | error
 * O job ProcessImport leva até `suggested`; `review` e `approved` são da tela de
 * revisão (Filament) e `integrated` da aprovação que cria livro/capítulos/pessoas.
 * `error` é reprocessável com reprocess().
 *
 * A tela de revisão (frente E2) guarda no próprio `result` o que foi aprovado:
 * `result.book.approved` e `result.chapters[i].approved` (bool), além dos campos
 * editados (`abstract_approved` por capítulo, correções de título, autores...).
 */
class Import extends Model
{
    public const RECEIVED = 'received';
    public const EXTRACTING = 'extracting';
    public const SEGMENTING = 'segmenting';
    public const SUGGESTED = 'suggested';
    public const REVIEW = 'review';
    public const APPROVED = 'approved';
    public const INTEGRATED = 'integrated';
    public const ERROR = 'error';

    public const STATUSES = [
        self::RECEIVED => 'Recebido',
        self::EXTRACTING => 'Extraindo texto',
        self::SEGMENTING => 'Segmentando capítulos',
        self::SUGGESTED => 'Proposta pronta',
        self::REVIEW => 'Em revisão',
        self::APPROVED => 'Aprovado',
        self::INTEGRATED => 'Integrado',
        self::ERROR => 'Erro',
    ];

    /** Pasta no disco `local` (storage/app/private). */
    public const DIR = 'imports';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'usage' => 'array',
            'checkpoint' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            self::RECEIVED, self::EXTRACTING, self::SEGMENTING => 'info',
            self::SUGGESTED, self::REVIEW => 'warning',
            self::APPROVED, self::INTEGRATED => 'success',
            self::ERROR => 'danger',
            default => 'gray',
        };
    }

    /** Caminho absoluto do PDF no disco privado. */
    public function absolutePath(): string
    {
        return Storage::disk('local')->path($this->file_path);
    }

    public function isProcessing(): bool
    {
        return in_array($this->status, [self::EXTRACTING, self::SEGMENTING], true);
    }

    /** Pode voltar à fila: falhou, ou ainda não foi aprovado por alguém (a revisão em curso se perde). */
    public function canReprocess(): bool
    {
        return in_array($this->status, [self::ERROR, self::RECEIVED, self::SUGGESTED, self::REVIEW], true);
    }

    /** Zera erro, proposta e ponto de retomada e volta a `received`: tudo é refeito (e pago) de novo. */
    public function reprocess(): void
    {
        $this->forceFill(['status' => self::RECEIVED, 'error' => null, 'result' => null, 'checkpoint' => null, 'approved_at' => null])->save();
    }

    /** Falhou no meio e guardou o que já foi pago (leitura da obra, capítulos prontos). */
    public function canResume(): bool
    {
        return $this->status === self::ERROR && filled($this->checkpoint['front'] ?? null);
    }

    /** Volta a `received` mantendo proposta e ponto de retomada: o job continua de onde parou. */
    public function resume(): void
    {
        $this->forceFill(['status' => self::RECEIVED, 'error' => null, 'approved_at' => null])->save();
    }

    /** O que a tela chama em "Reprocessar": continua se puder, senão refaz do zero. */
    public function retry(): void
    {
        $this->canResume() ? $this->resume() : $this->reprocess();
    }

    // ---- Revisão (frente E2) ------------------------------------------------

    /** Texto JSON de `result` que gerou o cache abaixo (o cast `array` decodifica a cada leitura). */
    private ?string $proposalRaw = null;

    private array $proposalData = [];

    /**
     * `result` decodificado uma vez por versão gravada. A tela de revisão lê a proposta
     * em cada campo; num livro de 74 capítulos (1 MB de JSON, ~6 ms por decodificação)
     * ler `$this->result` toda vez passava dos 30 s do PHP.
     */
    public function proposal(): array
    {
        $raw = $this->attributes['result'] ?? null;
        $raw = is_string($raw) ? $raw : null;
        if ($raw === null) {
            return [];
        }
        if ($raw !== $this->proposalRaw) {
            $decoded = json_decode($raw, true);
            $this->proposalData = is_array($decoded) ? $decoded : [];
            $this->proposalRaw = $raw;
        }

        return $this->proposalData;
    }

    /** Impressão digital da proposta gravada (detecta mudança feita por outra janela ou pela IA). */
    public function proposalHash(): string
    {
        $raw = $this->attributes['result'] ?? null;

        return md5(is_string($raw) ? $raw : 'null');
    }

    /**
     * Grava `result` só se a versão no banco ainda for a `$expectedHash` (a que a tela
     * carregou). Confere e grava dentro de uma transação com a linha travada: sem isso,
     * uma ação da tela que começou antes de outra gravação (IA, script, outra janela)
     * terminava depois e regravava a proposta velha por cima.
     */
    public function saveProposalIf(string $expectedHash, array $result): bool
    {
        return DB::transaction(function () use ($expectedHash, $result) {
            $current = self::query()->whereKey($this->getKey())->lockForUpdate()->first();
            if (! $current || $current->proposalHash() !== $expectedHash) {
                return false;
            }
            $this->forceFill(['result' => $result])->save();

            return true;
        });
    }

    /** Tem proposta para revisar/aprovar/integrar. */
    public function hasProposal(): bool
    {
        return in_array($this->status, [self::SUGGESTED, self::REVIEW, self::APPROVED, self::INTEGRATED], true)
            && filled($this->proposal()['book'] ?? null);
    }

    /** A tela ainda aceita edições e aprovações. */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::SUGGESTED, self::REVIEW, self::APPROVED], true) && $this->hasProposal();
    }

    public function bookApproved(): bool
    {
        return (bool) ($this->proposal()['book']['approved'] ?? false);
    }

    public function chapterApproved(int $index): bool
    {
        return (bool) ($this->proposal()['chapters'][$index]['approved'] ?? false);
    }

    public function chaptersCount(): int
    {
        return count($this->proposal()['chapters'] ?? []);
    }

    public function approvedChaptersCount(): int
    {
        return count(array_filter($this->proposal()['chapters'] ?? [], fn ($c) => ! empty($c['approved'])));
    }

    /** Obra aprovada e todos os capítulos (ao menos um) aprovados. */
    public function isFullyApproved(): bool
    {
        return $this->bookApproved() && $this->chaptersCount() > 0 && $this->approvedChaptersCount() === $this->chaptersCount();
    }

    /** "obra aprovada, 3 de 7 capítulos aprovados" */
    public function approvalSummary(): string
    {
        return ($this->bookApproved() ? 'obra aprovada' : 'obra ainda não aprovada')
            .sprintf(', %d de %d capítulo(s) aprovado(s)', $this->approvedChaptersCount(), $this->chaptersCount());
    }

    /** Custo estimado em US$ (imports.usage). */
    public function cost(): ?float
    {
        return isset($this->usage['cost_usd']) ? (float) $this->usage['cost_usd'] : null;
    }

    /** Guarda um PDF no disco privado e cria o registro em `received`. */
    public static function receive(string $sourcePath, ?string $originalName = null, ?int $userId = null, ?int $bookId = null): self
    {
        $originalName = $originalName ?: basename($sourcePath);
        $name = self::DIR.'/'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.pdf';

        Storage::disk('local')->put($name, file_get_contents($sourcePath));

        return static::create([
            'book_id' => $bookId,
            'file_path' => $name,
            'original_name' => $originalName,
            'size' => filesize($sourcePath),
            'status' => self::RECEIVED,
            'created_by' => $userId,
        ]);
    }
}
