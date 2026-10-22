<?php

namespace App\Editorial\Import\Ai;

/**
 * Única porta de saída para a API de IA na importação. O Analyzer só conhece
 * esta interface: em produção é o OpenAiGateway (SDK oficial, Responses API), nos
 * testes um fake que devolve JSON pronto. Nenhuma chamada real sai sem chave.
 */
interface AiGateway
{
    /**
     * Uma chamada com saída estruturada (JSON que obedece ao schema, modo strict).
     *
     * @param  string  $instructions  instrução fixa da tarefa (system/developer)
     * @param  list<array<string,mixed>>  $content  partes do turno do usuário (`input_text` e/ou `input_file` PDF)
     * @param  string  $schemaName  nome do schema (front_matter, chapter, chapter_part)
     * @param  array<string,mixed>  $schema  JSON Schema da resposta (todas as propriedades obrigatórias, additionalProperties false)
     */
    public function structured(string $instructions, array $content, string $schemaName, array $schema, int $maxTokens): StructuredResult;
}
