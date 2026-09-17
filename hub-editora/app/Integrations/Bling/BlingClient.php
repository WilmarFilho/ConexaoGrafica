<?php

namespace App\Integrations\Bling;

use App\Models\Channel;
use App\Models\Setting;
use App\Models\SyncLog;
use App\Support\HubSettings;
use App\Support\IntegrationAlerts;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente da API v3 do Bling (OAuth 2.0).
 *
 * O access token dura 6 h e o refresh token 30 dias; os dois ficam
 * criptografados na tabela settings e são renovados aqui sozinhos.
 * A conta precisa ser autorizada uma vez pelo botão "Conectar ao Bling".
 */
class BlingClient
{
    public const AUTHORIZE_URL = 'https://www.bling.com.br/Api/v3/oauth/authorize';
    public const TOKEN_URL = 'https://www.bling.com.br/Api/v3/oauth/token';

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $baseUrl,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('hub.bling.client_id'),
            (string) config('hub.bling.client_secret'),
            rtrim((string) config('hub.bling.base_url'), '/'),
        );
    }

    public static function isConfigured(): bool
    {
        return filled(config('hub.bling.client_id')) && filled(config('hub.bling.client_secret'));
    }

    public static function isConnected(): bool
    {
        return filled(self::token('refresh_token'));
    }

    // ---- OAuth -------------------------------------------------------------

    public function authorizeUrl(string $state): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'state' => $state,
        ]);
    }

    /** Troca o code do callback pelos tokens e guarda. */
    public function exchangeCode(string $code): void
    {
        $this->storeTokens($this->tokenRequest(['grant_type' => 'authorization_code', 'code' => $code]));
    }

    public function refresh(): void
    {
        $refresh = self::token('refresh_token');
        if (! $refresh) {
            throw new RuntimeException('Bling não conectado: autorize pelo botão "Conectar ao Bling".');
        }

        try {
            $this->storeTokens($this->tokenRequest(['grant_type' => 'refresh_token', 'refresh_token' => $refresh]));
        } catch (RuntimeException $e) {
            // Refresh recusado (revogado, app apagado, 30 dias sem uso): derruba a
            // conexão para a tela Integrações e o painel mostrarem "falta autorizar".
            if (str_contains($e->getMessage(), 'invalid_grant') || str_contains($e->getMessage(), '(400)') || str_contains($e->getMessage(), '(401)')) {
                Setting::query()->whereIn('key', ['bling.access_token', 'bling.refresh_token', 'bling.expires_at'])->delete();
                Cache::forget('hub.settings.v1');
                SyncLog::record(Channel::bySlug(Channel::BLING), SyncLog::IN, 'settings.tested', null,
                    'Bling desconectou: '.$e->getMessage().' — reconecte em Integrações.', [], 'error');
                IntegrationAlerts::down(Channel::BLING, 'O Bling recusou a renovação do acesso ('.$e->getMessage().'). É preciso reconectar.', immediate: true);
            }

            throw $e;
        }
    }

    private function tokenRequest(array $form): array
    {
        $res = Http::asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->acceptJson()
            ->timeout(30)
            ->post(self::TOKEN_URL, $form);

        if (! $res->successful()) {
            $body = $res->json();
            throw new RuntimeException('Bling OAuth ('.$res->status().'): '.($body['error_description'] ?? $body['error'] ?? $res->body()));
        }

        return (array) $res->json();
    }

    private function storeTokens(array $t): void
    {
        $set = fn (string $k, ?string $v) => Setting::query()->updateOrCreate(['key' => 'bling.'.$k], ['value' => $v]);
        $set('access_token', $t['access_token'] ?? null);
        $set('refresh_token', $t['refresh_token'] ?? null);
        $set('expires_at', (string) now()->addSeconds((int) ($t['expires_in'] ?? 21600))->subMinutes(5)->timestamp);
        $set('connected_at', (string) now()->timestamp);
        Cache::forget('hub.settings.v1');
    }

    public static function token(string $name): ?string
    {
        return HubSettings::stored()['bling.'.$name] ?? null;
    }

    public static function connectedAt(): ?\Illuminate\Support\Carbon
    {
        $ts = self::token('connected_at');

        return $ts ? \Illuminate\Support\Carbon::createFromTimestamp((int) $ts) : null;
    }

    private function accessToken(): string
    {
        $expires = (int) (self::token('expires_at') ?? 0);
        if (! self::token('access_token') || $expires <= now()->timestamp) {
            $this->refresh();
        }

        return (string) self::token('access_token');
    }

    // ---- API ---------------------------------------------------------------

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->accessToken())
            ->acceptJson()
            ->timeout(30);
    }

    /** GET com uma nova tentativa após renovar o token se o Bling devolver 401. */
    public function get(string $path, array $query = []): array
    {
        $res = $this->http()->get($path, $query);
        if ($res->status() === 401) {
            $this->refresh();
            $res = $this->http()->get($path, $query);
        }

        return $this->unwrap($res, $path);
    }

    public function put(string $path, array $payload): array
    {
        return $this->unwrap($this->http()->put($path, $payload), $path);
    }

    public function patch(string $path, array $payload = []): array
    {
        return $this->unwrap($this->http()->patch($path, $payload), $path);
    }

    /**
     * Pedidos de venda alterados desde uma data (lista resumida), paginando.
     *
     * @return \Generator<int, array>
     */
    public function ordersModifiedSince(\DateTimeInterface $since, int $limit = 100): \Generator
    {
        $page = 1;

        do {
            $body = $this->get('/pedidos/vendas', [
                'dataAlteracaoInicial' => $since->format('Y-m-d H:i:s'),
                'pagina' => $page,
                'limite' => $limit,
            ]);
            $rows = $body['data'] ?? [];

            foreach ($rows as $row) {
                yield $row;
            }

            $page++;
            usleep(350000); // o Bling limita a 3 req/s
        } while (count($rows) === $limit);
    }

    /** Detalhe completo (itens, transporte, contato). */
    public function order(int $id): array
    {
        return $this->get("/pedidos/vendas/{$id}")['data'] ?? [];
    }

    /** Serviços da logística "Melhor Envio" do Bling, indexados pelo nome (PAC, SEDEX...). */
    public function logisticsServices(): array
    {
        $out = [];
        foreach ($this->get('/logisticas/servicos')['data'] ?? [] as $s) {
            $out[mb_strtoupper((string) ($s['descricao'] ?? ''))] = [
                'id' => $s['id'],
                'alias' => $s['aliases'][0] ?? null,
                'carrier' => $s['nomeTransportador'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Devolve o rastreio ao pedido e o marca como atendido (situação 9).
     * É isso que faz o Bling confirmar o envio na Amazon.
     *
     * O Bling só aceita o rastreio em duas etapas: primeiro cria o volume com o
     * alias do serviço (ex.: ME_PAC_1), depois grava o código no volume criado.
     */
    public function markShipped(int $orderId, string $trackingCode, string $serviceName = 'PAC'): array
    {
        $services = $this->logisticsServices();
        $svc = $services[mb_strtoupper($serviceName)] ?? $services['PAC'] ?? reset($services);
        if (! $svc || empty($svc['alias'])) {
            throw new RuntimeException('Bling: nenhum serviço de logística configurado (integração Melhor Envio no Bling).');
        }

        $order = $this->order($orderId);
        if (! $order) {
            throw new RuntimeException("Bling: pedido {$orderId} não encontrado.");
        }

        $body = fn (array $volumes) => array_merge(
            array_diff_key($order, array_flip(['id', 'notaFiscal', 'taxas'])),
            ['transporte' => array_merge($order['transporte'] ?? [], ['quantidadeVolumes' => 1, 'volumes' => $volumes])],
        );

        $existing = collect($order['transporte']['volumes'] ?? [])->first();
        if (! $existing) {
            $res = $this->put("/pedidos/vendas/{$orderId}", $body([['servico' => $svc['alias'], 'codigoRastreamento' => $trackingCode]]));
            $volumeId = $res['data']['tracking']['volumes'][0]['id'] ?? $res['data']['tracking']['idsVolumes'][0] ?? null;
            usleep(500000);
        } else {
            $volumeId = $existing['id'];
        }

        if (! $volumeId) {
            throw new RuntimeException('Bling: não foi possível criar o volume do pedido.');
        }

        if (($existing['codigoRastreamento'] ?? '') !== $trackingCode) {
            $this->put("/pedidos/vendas/{$orderId}", $body([['id' => $volumeId, 'servico' => $svc['alias'], 'codigoRastreamento' => $trackingCode]]));
            usleep(500000);
        }

        $situacao = (int) ($order['situacao']['id'] ?? 0);
        if ($situacao !== 9) {
            $this->patch("/pedidos/vendas/{$orderId}/situacoes/9");
        }

        return ['volume_id' => $volumeId, 'service' => $svc['alias'], 'situacao_antes' => $situacao];
    }

    private function unwrap($res, string $path): array
    {
        if ($res->successful()) {
            return (array) $res->json();
        }

        $body = $res->json();
        $msg = $body['error']['description'] ?? $body['error']['message'] ?? $body['error']['type'] ?? $res->body();

        throw new RuntimeException("Bling {$path} ({$res->status()}): ".mb_substr((string) $msg, 0, 400));
    }
}
