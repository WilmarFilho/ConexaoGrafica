<?php

namespace App\Editorial\Import\Ai;

/**
 * Falha de uma chamada à IA que não é erro de rede: recusa (parte `refusal` na saída),
 * resposta cortada (status incomplete por max_output_tokens) ou JSON inválido.
 * `reason` é um dos REFUSAL|TRUNCATED|INVALID_JSON.
 */
class AiException extends \RuntimeException
{
    public const REFUSAL = 'refusal';
    public const TRUNCATED = 'truncated';
    public const INVALID_JSON = 'invalid_json';

    public function __construct(public readonly string $reason, string $message, public readonly array $usage = [])
    {
        parent::__construct($message);
    }
}
