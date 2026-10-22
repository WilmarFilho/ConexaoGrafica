<?php

namespace App\Editorial\Import\Ai;

/** Resposta de uma chamada estruturada: dados já decodificados e uso de tokens. */
final class StructuredResult
{
    /**
     * @param  array<string,mixed>  $data  JSON decodificado conforme o schema
     * @param  array{input_tokens:int, output_tokens:int, cached_input_tokens:int, reasoning_tokens:int}  $usage
     *         input_tokens inclui os cached_input_tokens (mais baratos); output_tokens inclui os reasoning_tokens
     */
    public function __construct(
        public readonly array $data,
        public readonly array $usage,
        public readonly string $model,
        public readonly ?string $status = null,
    ) {}

    /** Uso zerado, para fakes e para somar. */
    public static function emptyUsage(): array
    {
        return ['input_tokens' => 0, 'output_tokens' => 0, 'cached_input_tokens' => 0, 'reasoning_tokens' => 0];
    }
}
