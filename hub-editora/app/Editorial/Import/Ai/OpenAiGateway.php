<?php

namespace App\Editorial\Import\Ai;

use OpenAI;
use Illuminate\Support\Sleep;
use OpenAI\Contracts\ClientContract;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Exceptions\RateLimitException;
use OpenAI\Exceptions\ServerException;
use OpenAI\Exceptions\TransporterException;
use Psr\Http\Message\ResponseInterface;
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
    /** Limite de requisições/tokens da conta (429) e falhas passageiras: tenta de novo com espera crescente. */
    public const MAX_ATTEMPTS = 8;

    public const MAX_WAIT_SECONDS = 300;

    private ?ClientContract $client;

    public function __construct(?ClientContract $client = null)
    {
        $this->client = $client;
    }

    public function structured(string $instructions, array $content, string $schemaName, array $schema, int $maxTokens): StructuredResult
    {
        $model = (string) config('hub.ai.model');

        $response = $this->withRetries(fn () => $this->client()->responses()->create([
            'model' => $model,
            'instructions' => $instructions,
            'input' => [['role' => 'user', 'content' => $content]],
            'max_output_tokens' => $maxTokens,
            'reasoning' => ['effort' => (string) config('hub.ai.effort', 'medium')],
            'text' => ['format' => ['type' => 'json_schema', 'name' => $schemaName, 'schema' => $schema, 'strict' => true]],
            // Livros inéditos: não guardar a conversa do lado da OpenAI.
            'store' => false,
        ]));

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

    /**
     * Executa a chamada repetindo quando a OpenAI limita a conta (429) ou falha de
     * forma passageira (5xx, rede). Espera o que o cabeçalho pedir (retry-after,
     * x-ratelimit-reset-*) ou 15 s, 30 s, 60 s... até 5 min; desiste após MAX_ATTEMPTS.
     */
    private function withRetries(callable $call): mixed
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $call();
            } catch (RateLimitException|ServerException|TransporterException|ErrorException $e) {
                $status = match (true) {
                    $e instanceof RateLimitException => 429,
                    $e instanceof ErrorException => $e->getStatusCode(),
                    default => 0,
                };
                $transient = $e instanceof RateLimitException || $e instanceof ServerException
                    || $e instanceof TransporterException || in_array($status, [408, 409, 429, 500, 502, 503, 504], true);
                if (! $transient || $attempt >= self::MAX_ATTEMPTS) {
                    throw $e;
                }

                $response = property_exists($e, 'response') ? $e->response : null;
                Sleep::for($this->waitSeconds($response, $attempt))->seconds();
            }
        }
    }

    /** Segundos de espera: o que a API indicar, senão 15 s dobrando a cada tentativa (máximo 5 min). */
    public function waitSeconds(?ResponseInterface $response, int $attempt): int
    {
        $hinted = 0;
        if ($response) {
            $retryAfter = trim($response->getHeaderLine('retry-after'));
            if ($retryAfter !== '' && is_numeric($retryAfter)) {
                $hinted = (int) ceil((float) $retryAfter);
            }
            foreach (['x-ratelimit-reset-requests', 'x-ratelimit-reset-tokens'] as $h) {
                $hinted = max($hinted, self::durationToSeconds($response->getHeaderLine($h)));
            }
        }
        $backoff = 15 * (2 ** ($attempt - 1));

        return (int) min(self::MAX_WAIT_SECONDS, max($hinted + 1, $hinted > 0 ? 0 : $backoff));
    }

    /** "6m0s", "1.5s", "250ms" (formato dos cabeçalhos da OpenAI) → segundos, arredondado para cima. */
    public static function durationToSeconds(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $total = 0.0;
        if (preg_match_all('/(\d+(?:\.\d+)?)(ms|h|m|s)/', $value, $m, PREG_SET_ORDER)) {
            foreach ($m as [, $n, $unit]) {
                $total += (float) $n * ['ms' => 0.001, 's' => 1, 'm' => 60, 'h' => 3600][$unit];
            }
        } elseif (is_numeric($value)) {
            $total = (float) $value;
        }

        return (int) ceil($total);
    }
}
