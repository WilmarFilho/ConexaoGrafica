<?php

namespace App\Crossref;

use DOMDocument;
use DOMElement;

/**
 * Relatório de processamento de um lote (`doi_batch_diagnostic`, obtido em
 * servlet/submissionDownload?type=result). É daqui, e só daqui, que um DOI
 * passa a `registered` ou `failed`.
 */
final class BatchResult
{
    public const COMPLETED = 'completed';

    public const PENDING = 'pending';   // ainda na fila da Crossref, ou relatório não disponível

    public const ERROR = 'error';       // resposta inesperada (HTTP de erro, login inválido...)

    /**
     * @param  list<array{doi: string, status: string, message: string}>  $records  status = Success | Warning | Failure
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $batchId,
        public readonly array $records,
        public readonly int $successCount,
        public readonly int $warningCount,
        public readonly int $failureCount,
        public readonly string $message,
        public readonly int $httpStatus,
        public readonly string $raw,
    ) {}

    public static function fromHttp(int $status, string $body): self
    {
        if ($status < 200 || $status >= 300) {
            return self::other(self::ERROR, "Crossref respondeu HTTP {$status} ao consultar o resultado.", $status, $body);
        }

        $doc = self::parse($body);
        $root = $doc?->documentElement;

        if (! $root || $root->localName !== 'doi_batch_diagnostic') {
            // Texto/HTML: lote ainda não processado, nome de arquivo desconhecido, login inválido...
            $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($body))) ?? $body);
            $kind = preg_match('/(not found|unknown|still being processed|in progress|queued)/i', $text) ? self::PENDING : self::ERROR;
            $text = $text !== '' ? mb_substr($text, 0, 500) : 'Resposta vazia da Crossref.';

            return self::other($kind, $text, $status, $body);
        }

        $batchStatus = strtolower($root->getAttribute('status'));
        $records = [];

        foreach ($root->getElementsByTagName('record_diagnostic') as $rec) {
            /** @var DOMElement $rec */
            $records[] = [
                'doi' => trim(self::child($rec, 'doi')),
                'status' => ucfirst(strtolower($rec->getAttribute('status'))) ?: 'Failure',
                'message' => trim(preg_replace('/\s+/u', ' ', self::child($rec, 'msg')) ?? ''),
            ];
        }

        $batchId = trim(self::child($root, 'batch_id')) ?: null;
        $counts = fn (string $n) => (int) self::child($root, $n);

        $completed = $batchStatus === 'completed' || ($batchStatus === '' && $records !== []);

        return new self(
            $completed ? self::COMPLETED : self::PENDING,
            $batchId,
            $records,
            $counts('success_count'),
            $counts('warning_count'),
            $counts('failure_count'),
            $completed
                ? sprintf('%d registro(s): %d ok, %d com aviso, %d com falha', count($records), $counts('success_count'), $counts('warning_count'), $counts('failure_count'))
                : 'Lote ainda em processamento na Crossref'.($batchStatus ? " (status {$batchStatus})" : '').'.',
            $status,
            $body,
        );
    }

    public function isCompleted(): bool
    {
        return $this->status === self::COMPLETED;
    }

    /** @return array{doi: string, status: string, message: string}|null */
    public function record(string $doi): ?array
    {
        $doi = strtolower(trim($doi));
        foreach ($this->records as $r) {
            if (strtolower($r['doi']) === $doi) {
                return $r;
            }
        }

        return null;
    }

    private static function other(string $kind, string $message, int $status, string $body): self
    {
        return new self($kind, null, [], 0, 0, 0, $message, $status, $body);
    }

    private static function parse(string $body): ?DOMDocument
    {
        if (! str_contains($body, '<')) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $doc = new DOMDocument;
            $ok = $doc->loadXML($body, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOERROR | LIBXML_NOWARNING);

            return $ok ? $doc : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function child(DOMElement $el, string $name): string
    {
        foreach ($el->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName === $name) {
                return $node->textContent;
            }
        }

        // batch_data/<count> ficam um nível abaixo
        foreach ($el->getElementsByTagName($name) as $node) {
            return $node->textContent;
        }

        return '';
    }
}
