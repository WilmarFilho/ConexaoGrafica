<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Importação de um livro em PDF com IA (Conexão DOI, frente C).
 *
 * Estados (contrato, seção 3):
 *   received → extracting → segmenting → suggested → review → approved → integrated | error
 * O job ProcessImport leva até `suggested`; `review` e `approved` são da tela de
 * revisão (Filament) e `integrated` da aprovação que cria livro/capítulos/pessoas.
 * `error` é reprocessável com reprocess().
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

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
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

    /** Pode voltar à fila: falhou, ou ainda não foi revisado por alguém. */
    public function canReprocess(): bool
    {
        return in_array($this->status, [self::ERROR, self::RECEIVED, self::SUGGESTED], true);
    }

    /** Zera erro e proposta e volta a `received`, pronto para ProcessImport::dispatch(). */
    public function reprocess(): void
    {
        $this->forceFill(['status' => self::RECEIVED, 'error' => null, 'result' => null])->save();
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
