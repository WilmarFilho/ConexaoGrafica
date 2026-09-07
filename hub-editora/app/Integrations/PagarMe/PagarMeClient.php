<?php

namespace App\Integrations\PagarMe;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Cliente da API Core v5 do Pagar.me.
 *
 * Autentica com a chave secreta (sk_...) em Basic Auth, usuário = chave e
 * senha vazia. A chave é do hub, criada no Dash — não é o token do Hub do
 * WooCommerce (acs_...), que só serve para o módulo da loja.
 */
class PagarMeClient
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $baseUrl,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('hub.pagarme.secret_key'),
            rtrim((string) config('hub.pagarme.base_url'), '/'),
        );
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withBasicAuth($this->secretKey, '')
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500, throw: false);
    }

    /**
     * Pedidos criados a partir de uma data, paginando.
     *
     * @return \Generator<int, array>
     */
    public function ordersCreatedSince(\DateTimeInterface $since, int $size = 50): \Generator
    {
        $page = 1;

        do {
            $response = $this->http()->get('/orders', [
                'created_since' => $since->format('Y-m-d\TH:i:s'),
                'size' => $size,
                'page' => $page,
            ])->throw();

            $body = $response->json();
            $items = $body['data'] ?? [];

            foreach ($items as $order) {
                yield $order;
            }

            $hasNext = ! empty($body['paging']['next']);
            $page++;
        } while ($hasNext && count($items) === $size);
    }

    public function order(string $id): array
    {
        return $this->http()->get("/orders/{$id}")->throw()->json();
    }
}
