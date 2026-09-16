<?php

namespace App\Integrations\Amazon;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Selling Partner API da Amazon (Orders v0), com app privado autorizado na
 * própria conta. Desde 2023 basta o token do Login with Amazon: sem AWS/SigV4.
 *
 * Uso no hub: confirmar o envio com rastreio ("Confirmar envio" do Seller
 * Central) assim que a etiqueta sai. Os pedidos em si continuam vindo pelo Bling.
 */
class AmazonClient
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $refreshToken,
        private readonly string $marketplaceId,
        private readonly string $endpoint,
        private readonly string $tokenUrl,
    ) {}

    public static function fromConfig(): self
    {
        $c = config('hub.amazon');

        return new self(
            (string) $c['client_id'], (string) $c['client_secret'], (string) $c['refresh_token'],
            (string) $c['marketplace_id'], rtrim((string) $c['endpoint'], '/'), (string) $c['token_url'],
        );
    }

    public static function isConfigured(): bool
    {
        $c = config('hub.amazon');

        return filled($c['client_id'] ?? null) && filled($c['client_secret'] ?? null) && filled($c['refresh_token'] ?? null);
    }

    // ---- Login with Amazon -------------------------------------------------

    private function accessToken(): string
    {
        $key = 'hub.amazon.access_token.'.md5($this->clientId.$this->refreshToken);

        return Cache::remember($key, now()->addMinutes(50), function () {
            $res = Http::asForm()->timeout(30)->post($this->tokenUrl, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->refreshToken,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

            if (! $res->successful() || empty($res->json('access_token'))) {
                $b = $res->json();
                throw new RuntimeException('Amazon LWA ('.$res->status().'): '.($b['error_description'] ?? $b['error'] ?? $res->body()));
            }

            return (string) $res->json('access_token');
        });
    }

    public function forgetToken(): void
    {
        Cache::forget('hub.amazon.access_token.'.md5($this->clientId.$this->refreshToken));
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->endpoint)
            ->withHeaders(['x-amz-access-token' => $this->accessToken()])
            ->acceptJson()
            ->timeout(40);
    }

    // ---- Orders v0 -----------------------------------------------------------

    /** Últimos pedidos (teste de conexão e conferência). */
    public function recentOrders(int $days = 7, int $max = 5): array
    {
        return $this->unwrap($this->http()->get('/orders/v0/orders', [
            'MarketplaceIds' => $this->marketplaceId,
            'CreatedAfter' => now()->subDays($days)->toIso8601ZuluString(),
            'MaxResultsPerPage' => $max,
        ]))['payload']['Orders'] ?? [];
    }

    public function order(string $amazonOrderId): array
    {
        return $this->unwrap($this->http()->get("/orders/v0/orders/{$amazonOrderId}"))['payload'] ?? [];
    }

    /** Itens com OrderItemId, necessários na confirmação de envio. */
    public function orderItems(string $amazonOrderId): array
    {
        return $this->unwrap($this->http()->get("/orders/v0/orders/{$amazonOrderId}/orderItems"))['payload']['OrderItems'] ?? [];
    }

    /**
     * "Confirmar envio" com rastreio. Envia todos os itens do pedido num único
     * pacote. carrierCode segue a lista da Amazon; para transportadora fora da
     * lista, usa "Other" + carrierName.
     */
    public function confirmShipment(string $amazonOrderId, string $trackingNumber, string $carrier = 'Correios', ?string $shippingMethod = null, ?\DateTimeInterface $shipDate = null): void
    {
        $items = $this->orderItems($amazonOrderId);
        if (! $items) {
            throw new RuntimeException("Amazon: pedido {$amazonOrderId} sem itens para confirmar.");
        }

        $known = ['Correios', 'Jadlog', 'Loggi', 'Total Express', 'Azul Cargo', 'Sequoia', 'DHL', 'FedEx', 'UPS'];
        $carrierCode = collect($known)->first(fn ($k) => strcasecmp($k, trim($carrier)) === 0) ?? 'Other';

        $payload = [
            'marketplaceId' => $this->marketplaceId,
            'packageDetail' => [
                'packageReferenceId' => '1',
                'carrierCode' => $carrierCode,
                'carrierName' => $carrier,
                'shippingMethod' => $shippingMethod ?: $carrier,
                'trackingNumber' => $trackingNumber,
                'shipDate' => ($shipDate ?? now())->format('Y-m-d\TH:i:s\Z'),
                'orderItems' => array_map(fn ($i) => [
                    'orderItemId' => $i['OrderItemId'],
                    'quantity' => (int) ($i['QuantityOrdered'] ?? 1),
                ], $items),
            ],
        ];

        $res = $this->http()->post("/orders/v0/orders/{$amazonOrderId}/shipmentConfirmation", $payload);

        if ($res->status() === 204 || $res->successful()) {
            return;
        }

        $this->unwrap($res); // lança com a mensagem da Amazon
    }

    private function unwrap(Response $res): array
    {
        if ($res->successful()) {
            return (array) $res->json();
        }

        if ($res->status() === 401 || $res->status() === 403) {
            $this->forgetToken();
        }

        $errors = $res->json('errors') ?? [];
        $msg = $errors ? collect($errors)->map(fn ($e) => ($e['code'] ?? '').': '.($e['message'] ?? '').(isset($e['details']) ? ' ('.$e['details'].')' : ''))->implode(' | ') : $res->body();

        throw new RuntimeException('Amazon SP-API ('.$res->status().'): '.mb_substr((string) $msg, 0, 500));
    }
}
