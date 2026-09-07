<?php

namespace App\Integrations\WooCommerce;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Cliente mínimo da REST API v3 do WooCommerce.
 *
 * Autentica por consumer key/secret em Basic Auth sobre HTTPS — o modo
 * recomendado quando a loja está em HTTPS, como a Pubcon.
 */
class WooCommerceClient
{
    public function __construct(
        private readonly string $url,
        private readonly string $key,
        private readonly string $secret,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            rtrim((string) config('hub.woocommerce.url'), '/'),
            (string) config('hub.woocommerce.key'),
            (string) config('hub.woocommerce.secret'),
        );
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->url . '/wp-json/wc/v3')
            ->withBasicAuth($this->key, $this->secret)
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500, throw: false);
    }

    /**
     * Pedidos modificados a partir de uma data, paginando até acabar.
     *
     * @return \Generator<int, array>
     */
    public function ordersModifiedSince(\DateTimeInterface $since, int $perPage = 50): \Generator
    {
        $page = 1;

        do {
            $response = $this->http()->get('/orders', [
                'modified_after' => $since->format(\DateTimeInterface::ATOM),
                'per_page' => $perPage,
                'page' => $page,
                'orderby' => 'modified',
                'order' => 'asc',
            ]);

            $response->throw();

            $orders = $response->json();

            foreach ((array) $orders as $order) {
                yield $order;
            }

            $totalPages = (int) $response->header('X-WP-TotalPages');
            $page++;
        } while ($page <= $totalPages);
    }

    public function order(int $id): array
    {
        return $this->http()->get("/orders/{$id}")->throw()->json();
    }

    /**
     * Devolve o rastreio ao pedido e o marca como concluído.
     * O rastreio vai como nota do pedido (visível ao cliente) e em meta_data,
     * que plugins de rastreamento costumam ler.
     *
     * @throws RequestException
     */
    public function markShipped(int $id, string $trackingCode, string $carrier): array
    {
        $this->http()->post("/orders/{$id}/notes", [
            'note' => "Pedido enviado via {$carrier}. Código de rastreio: {$trackingCode}",
            'customer_note' => true,
        ])->throw();

        return $this->http()->put("/orders/{$id}", [
            'status' => 'completed',
            'meta_data' => [
                ['key' => '_hub_tracking_code', 'value' => $trackingCode],
                ['key' => '_hub_carrier', 'value' => $carrier],
            ],
        ])->throw()->json();
    }
}
