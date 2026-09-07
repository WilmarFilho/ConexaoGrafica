<?php

namespace App\Integrations\MelhorEnvio;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente fino da API v2 do Melhor Envio (cotação → carrinho → compra →
 * geração → impressão → rastreio). Só fala HTTP; regras ficam no ShipmentService.
 *
 * Docs: https://docs.melhorenvio.com.br
 */
class MelhorEnvioClient
{
    public function __construct(
        private readonly string $token,
        private readonly string $baseUrl,
        private readonly string $userAgent,
    ) {}

    public static function make(): self
    {
        $cfg = config('hub.melhor_envio');

        if (empty($cfg['token'])) {
            throw new RuntimeException('Melhor Envio: ME_TOKEN não configurado.');
        }

        return new self($cfg['token'], rtrim($cfg['base_url'], '/'), $cfg['user_agent']);
    }

    public static function isConfigured(): bool
    {
        return ! empty(config('hub.melhor_envio.token')) && ! empty(config('hub.melhor_envio.from.postal_code'));
    }

    /** Cotação: lista de serviços com preço e prazo (os com `error` vêm junto; filtramos depois). */
    public function calculate(array $payload): array
    {
        return $this->post('/me/shipment/calculate', $payload);
    }

    /** Adiciona um envio ao carrinho. Retorna o envio (com `id` do ME). */
    public function addToCart(array $payload): array
    {
        return $this->post('/me/cart', $payload);
    }

    /** Paga os envios do carrinho (usa o saldo da conta ME). */
    public function checkout(array $shipmentIds): array
    {
        return $this->post('/me/shipment/checkout', ['orders' => array_values($shipmentIds)]);
    }

    /** Gera as etiquetas (só depois do checkout). */
    public function generate(array $shipmentIds): array
    {
        return $this->post('/me/shipment/generate', ['orders' => array_values($shipmentIds)]);
    }

    /** URL do PDF das etiquetas (uma página por envio). */
    public function printUrl(array $shipmentIds, string $mode = 'private'): string
    {
        $res = $this->post('/me/shipment/print', ['mode' => $mode, 'orders' => array_values($shipmentIds)]);

        return $res['url'] ?? throw new RuntimeException('Melhor Envio: resposta de impressão sem URL.');
    }

    /** Situação/rastreio de cada envio, indexado pelo id do ME. */
    public function tracking(array $shipmentIds): array
    {
        return $this->post('/me/shipment/tracking', ['orders' => array_values($shipmentIds)]);
    }

    /** Cancela um envio ainda não postado. */
    public function cancel(string $shipmentId, string $reason = 'Cancelado pelo Hub Editora'): array
    {
        return $this->post('/me/shipment/cancel', [
            'order' => ['id' => $shipmentId, 'reason_id' => 2, 'description' => $reason],
        ]);
    }

    /** Saldo da carteira, útil para avisar antes de um lote. */
    public function balance(): array
    {
        return $this->get('/me/balance');
    }

    // ------------------------------------------------------------------

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->token)
            ->withHeaders(['User-Agent' => $this->userAgent])
            ->acceptJson()
            ->asJson()
            ->timeout(40);
    }

    private function post(string $path, array $payload): array
    {
        return $this->unwrap($this->http()->post($path, $payload), $path);
    }

    private function get(string $path, array $query = []): array
    {
        return $this->unwrap($this->http()->get($path, $query), $path);
    }

    private function unwrap(Response $res, string $path): array
    {
        if ($res->successful()) {
            return (array) $res->json();
        }

        $body = $res->json();
        $msg = $body['message'] ?? $body['error'] ?? $res->body();

        if (! empty($body['errors']) && is_array($body['errors'])) {
            $msg .= ' — '.collect($body['errors'])->flatten()->implode('; ');
        }

        throw new RuntimeException("Melhor Envio {$path} ({$res->status()}): ".mb_substr((string) $msg, 0, 500));
    }
}
