<?php

namespace App\Crossref;

/**
 * Resposta do servlet de depósito da Crossref (HTML). `accepted` só diz que o
 * arquivo foi recebido para processamento: nenhum DOI está registrado ainda.
 */
final class DepositResponse
{
    public function __construct(
        public readonly bool $accepted,
        public readonly int $httpStatus,
        public readonly string $message,
        public readonly string $body,
    ) {}

    /** Interpreta o HTML da Crossref: sucesso = "SUCCESS" no corpo com HTTP 2xx; "FAILURE" ou outro status = recusa. */
    public static function fromHttp(int $status, string $body): self
    {
        $text = self::plainText($body);
        $success = $status >= 200 && $status < 300
            && preg_match('/\bSUCCESS\b/', $text) === 1
            && preg_match('/\bFAILURE\b/', $text) !== 1;

        $message = trim(preg_replace('/^\s*(SUCCESS|FAILURE)\s*/', '', $text) ?? $text);
        if ($message === '') {
            $message = $success ? 'Arquivo recebido pela Crossref para processamento.' : "Crossref recusou o depósito (HTTP {$status}).";
        }

        return new self($success, $status, mb_substr($message, 0, 1000), $body);
    }

    private static function plainText(string $html): string
    {
        $text = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $text = preg_replace('#<title>.*?</title>#is', ' ', $text) ?? $text; // o <title> repete o <h2>
        $text = preg_replace('/<[^>]+>/', ' ', $text) ?? $text;              // "</h2><p>" sem espaço colaria as palavras
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
