<?php

namespace App\Editorial\Import\Ai;

use OpenAI;
use OpenAI\Contracts\ClientContract;
use OpenAI\Responses\Responses\Output\OutputMessage;
use OpenAI\Responses\Responses\Output\OutputMessageContentRefusal;

/**
 * Implementação com o SDK oficial de PHP da OpenAI (openai-php/client), pela
 * Responses API com saída estruturada (`text.format` json_schema, strict): o JSON
 * devolvido sempre obedece ao schema. O raciocínio do modelo vem de `reasoning.effort`
 * (config hub.ai.effort) e os tokens de raciocínio contam como saída.
 */
class OpenAiGateway implements AiGateway
{
    private ?ClientContract $client;

    public function __construct(?ClientContract $client = null)
    {
        $this->client = $client;
    }

    public function structured(string $instructions, array $content, string $schemaName, array $schema, int $maxTokens): StructuredResult
    {
        $model = (string) config('hub.ai.model');

        $response = $this->client()->responses()->create([
            'model' => $model,
            'instructions' => $instructions,
            'input' => [['role' => 'user', 'content' => $content]],
            'max_output_tokens' => $maxTokens,
            'reasoning' => ['effort' => (string) config('hub.ai.effort', 'medium')],
            'text' => ['format' => ['type' => 'json_schema', 'name' => $schemaName, 'schema' => $schema, 'strict' => true]],
            // Livros inéditos: não guardar a conversa do lado da OpenAI.
            'store' => false,
        ]);

        $usage = [
            'input_tokens' => (int) ($response->usage?->inputTokens ?? 0),
            'output_tokens' => (int) ($response->usage?->outputTokens ?? 0),
            'cached_input_tokens' => (int) ($response->usage?->inputTokensDetails?->cachedTokens ?? 0),
            'reasoning_tokens' => (int) ($response->usage?->outputTokensDetails?->reasoningTokens ?? 0),
        ];

        if ($response->status === 'incomplete') {
            $reason = $response->incompleteDetails?->reason ?? 'desconhecido';
            if ($reason === 'max_output_tokens') {
                throw new AiException(AiException::TRUNCATED, "Resposta cortada em {$maxTokens} tokens (inclui raciocínio); aumente HUB_AI_MAX_OUTPUT_TOKENS ou divida a entrada.", $usage);
            }
            throw new AiException(AiException::INVALID_JSON, "Resposta incompleta ({$reason}).", $usage);
        }
        if ($response->status !== 'completed') {
            throw new AiException(AiException::INVALID_JSON, "A chamada terminou com status {$response->status}.", $usage);
        }

        $text = '';
        foreach ($response->output as $item) {
            if (! $item instanceof OutputMessage) {
                continue;
            }
            foreach ($item->content as $part) {
                if ($part instanceof OutputMessageContentRefusal) {
                    throw new AiException(AiException::REFUSAL, 'O modelo recusou a solicitação: '.mb_substr($part->refusal, 0, 200), $usage);
                }
                $text .= $part->text;
            }
        }

        $data = json_decode($text, true);
        if (! is_array($data)) {
            throw new AiException(AiException::INVALID_JSON, 'A resposta do modelo não é um JSON válido: '.mb_substr($text, 0, 200), $usage);
        }

        return new StructuredResult($data, $usage, $response->model ?: $model, $response->status);
    }

    private function client(): ClientContract
    {
        if ($this->client === null) {
            $key = (string) config('hub.ai.key');
            if ($key === '') {
                throw new \RuntimeException('OPENAI_API_KEY não configurada.');
            }
            // Livro grande: uma chamada pode levar minutos; o Guzzle padrão não tem timeout.
            $this->client = OpenAI::factory()
                ->withApiKey($key)
                ->withHttpClient(new \GuzzleHttp\Client(['timeout' => 600, 'connect_timeout' => 15]))
                ->make();
        }

        return $this->client;
    }
}
