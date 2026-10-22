<?php

namespace App\Editorial\Import\Ai;

use Anthropic\Client;
use Psr\Http\Client\ClientInterface;

/**
 * Implementação com o SDK oficial de PHP da Anthropic (anthropic-ai/sdk), pela
 * Messages API com saída estruturada (`output_config.format` json_schema): o texto
 * devolvido é JSON que obedece ao schema. A profundidade de raciocínio vem de
 * `output_config.effort` (config hub.ai.effort); o raciocínio é sempre adaptativo.
 *
 * Recebe o conteúdo no mesmo formato do OpenAiGateway (input_file/input_text) e
 * converte para os blocos da Anthropic, para o Analyzer não saber qual provedor usa.
 * Limite de requisições (429) e falhas passageiras: o próprio SDK espera e repete,
 * respeitando retry-after.
 *
 * Os schemas do Analyzer usam "tipo ou null" em dezenas de campos (formato estrito da
 * OpenAI), e a Anthropic aceita no máximo 16 campos com união e 24 opcionais. Por isso,
 * só aqui, todo campo vira obrigatório e não nulo, o modelo responde "" ou 0 quando não
 * achar a informação, e na volta esses vazios voltam a ser null (restoreNulls).
 */
class AnthropicGateway implements AiGateway
{
    /** Acrescentado às instruções: como dizer "não achei" sem null. */
    public const EMPTY_RULE = "

Formato da resposta: todos os campos são obrigatórios. Quando a informação não existir no documento, responda \"\" para texto e 0 para número (nunca invente um valor).";

    /** Uma chamada de capítulo grande pode levar minutos. */
    public const TIMEOUT_SECONDS = 900.0;

    public const MAX_RETRIES = 8;

    private ?Client $client;

    /**
     * @param  ClientInterface|null  $transporter  cliente HTTP (os testes passam um simulado)
     * @param  array<string, mixed>  $requestOptions  sobrescreve tempo limite e esperas (testes)
     */
    public function __construct(?Client $client = null, private ?ClientInterface $transporter = null, private array $requestOptions = [])
    {
        $this->client = $client;
    }

    public function structured(string $instructions, array $content, string $schemaName, array $schema, int $maxTokens): StructuredResult
    {
        $model = (string) config('hub.ai.model');

        $message = $this->client()->messages->create(
            maxTokens: $maxTokens,
            messages: [['role' => 'user', 'content' => $this->blocks($content)]],
            model: $model,
            // Instruções iguais em todas as chamadas do mesmo livro: ficam em cache.
            system: [['type' => 'text', 'text' => $instructions.self::EMPTY_RULE, 'cacheControl' => ['type' => 'ephemeral']]],
            outputConfig: [
                'effort' => (string) config('hub.ai.effort', 'medium'),
                'format' => ['type' => 'json_schema', 'schema' => self::withoutNulls($schema)],
            ],
            requestOptions: $this->options(),
        );

        $usage = [
            'input_tokens' => (int) $message->usage->inputTokens + (int) ($message->usage->cacheCreationInputTokens ?? 0) + (int) ($message->usage->cacheReadInputTokens ?? 0),
            'output_tokens' => (int) $message->usage->outputTokens,
            'cached_input_tokens' => (int) ($message->usage->cacheReadInputTokens ?? 0),
            'reasoning_tokens' => 0, // a Anthropic conta o raciocínio dentro de output_tokens
        ];

        $stop = (string) $message->stopReason;
        if ($stop === 'refusal') {
            $why = $message->stopDetails?->explanation ?? $message->stopDetails?->category ?? 'sem detalhe';
            throw new AiException(AiException::REFUSAL, 'O modelo recusou a solicitação: '.mb_substr((string) $why, 0, 200), $usage);
        }
        if (in_array($stop, ['max_tokens', 'model_context_window_exceeded'], true)) {
            throw new AiException(AiException::TRUNCATED, "Resposta cortada em {$maxTokens} tokens (inclui raciocínio); aumente HUB_AI_MAX_OUTPUT_TOKENS ou divida a entrada.", $usage);
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        $data = json_decode($text, true);
        if (! is_array($data)) {
            throw new AiException(AiException::INVALID_JSON, 'A resposta do modelo não é um JSON válido: '.mb_substr($text, 0, 200), $usage);
        }

        return new StructuredResult(self::restoreNulls($data, $schema), $usage, (string) ($message->model ?: $model), 'completed');
    }

    /** Troca `"type": [T, "null"]` por `"type": T` em todo o schema (sem uniões para a Anthropic). */
    public static function withoutNulls(array $schema): array
    {
        if (is_array($schema['type'] ?? null) && in_array('null', $schema['type'], true)) {
            $types = array_values(array_diff($schema['type'], ['null']));
            $schema['type'] = count($types) === 1 ? $types[0] : $types;
        }
        foreach ($schema['properties'] ?? [] as $name => $prop) {
            $schema['properties'][$name] = self::withoutNulls($prop);
        }
        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = self::withoutNulls($schema['items']);
        }

        return $schema;
    }

    /** Onde o schema original aceitava null, "" e 0 voltam a ser null (o "não achei" do modelo). */
    public static function restoreNulls(mixed $value, array $schema): mixed
    {
        $nullable = is_array($schema['type'] ?? null) && in_array('null', $schema['type'], true);
        if ($nullable && ($value === '' || $value === 0 || $value === 0.0 || (is_string($value) && trim($value) === ''))) {
            return null;
        }
        if (is_array($value) && isset($schema['properties'])) {
            foreach ($schema['properties'] as $name => $prop) {
                if (array_key_exists($name, $value)) {
                    $value[$name] = self::restoreNulls($value[$name], $prop);
                }
            }
        } elseif (is_array($value) && isset($schema['items']) && array_is_list($value)) {
            foreach ($value as $i => $item) {
                $value[$i] = self::restoreNulls($item, $schema['items']);
            }
        }

        return $value;
    }

    /**
     * input_file (PDF em data URI) → document base64; input_text → text.
     *
     * @param  list<array<string, mixed>>  $content
     * @return list<array<string, mixed>>
     */
    public function blocks(array $content): array
    {
        $out = [];
        foreach ($content as $block) {
            $type = $block['type'] ?? null;
            if ($type === 'input_file') {
                $data = (string) ($block['file_data'] ?? '');
                $data = preg_replace('#^data:application/pdf;base64,#', '', $data);
                $out[] = [
                    'type' => 'document',
                    'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => $data],
                    'title' => $block['filename'] ?? null,
                ];
            } elseif ($type === 'input_text') {
                $out[] = ['type' => 'text', 'text' => (string) ($block['text'] ?? '')];
            }
        }

        return $out;
    }

    private function options(): array
    {
        return array_filter($this->requestOptions + [
            'timeout' => self::TIMEOUT_SECONDS,
            'maxRetries' => self::MAX_RETRIES,
            'initialRetryDelay' => 15.0,
            'maxRetryDelay' => 300.0,
            'transporter' => $this->transporter,
        ], fn ($v) => $v !== null);
    }

    private function client(): Client
    {
        if ($this->client) {
            return $this->client;
        }

        $key = (string) config('hub.ai.key');
        if ($key === '') {
            throw new \RuntimeException('ANTHROPIC_API_KEY não configurada.');
        }

        return $this->client = new Client(apiKey: $key);
    }
}
