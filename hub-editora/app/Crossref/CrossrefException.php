<?php

namespace App\Crossref;

use RuntimeException;

/**
 * Erro de negócio da integração Crossref: payload incompleto, XML inválido,
 * depósito recusado. A mensagem é em português e pode ir direto para a tela.
 */
class CrossrefException extends RuntimeException
{
    /** @param list<string> $errors */
    public function __construct(string $message, public readonly array $errors = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /** @param list<string> $errors */
    public static function invalidXml(array $errors): self
    {
        $n = count($errors);

        return new self(
            sprintf('O XML não passou no schema da Crossref (%d erro%s): %s', $n, $n === 1 ? '' : 's', implode(' | ', array_slice($errors, 0, 3))),
            $errors,
        );
    }
}
