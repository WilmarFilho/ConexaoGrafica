<?php

namespace App\Editorial\WordPress;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente da API interna do plugin `conexao-doi` (contrato, seção 4).
 *
 * Autenticação sem usuário WP: cabeçalhos `X-Conexao-Timestamp` (unix) e
 * `X-Conexao-Signature` = hex(hmac_sha256(timestamp . "\n" . corpo, segredo)).
 * O segredo é o CONEXAO_DOI_WP_SECRET do .env (CONEXAO_DOI_SECRET no wp-config).
 */
class Client
{
    public const BASE_PATH = '/wp-json/conexao-doi/v1';

    public function __construct(
        private readonly string $url,
        private readonly string $secret,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            rtrim((string) config('hub.wordpress.url'), '/'),
            (string) config('hub.wordpress.secret'),
        );
    }

    public static function isConfigured(): bool
    {
        return filled(config('hub.wordpress.url')) && filled(config('hub.wordpress.secret'));
    }

    public static function sign(string $timestamp, string $body, string $secret): string
    {
        return hash_hmac('sha256', $timestamp."\n".$body, $secret);
    }

    /** Cria ou atualiza o livro no site (idempotente). */
    public function publish(int $hubBookId, array $payload): array
    {
        return $this->send('put', "/publicacoes/{$hubBookId}", $payload);
    }

    /** Marca fora de catálogo; a página continua. */
    public function unpublish(int $hubBookId): array
    {
        return $this->send('post', "/publicacoes/{$hubBookId}/despublicar", []);
    }

    /**
     * Estado atual no WP, para conferência:
     * {book:{wp_post_id,url,slug,old_slugs,doi,status,doi_status,received_at,documents}, chapters:[…], people:[…]}.
     */
    public function state(int $hubBookId): array
    {
        return $this->send('get', "/publicacoes/{$hubBookId}", []);
    }

    /**
     * Upload da ficha PDF (frente E gera o arquivo). JSON com o conteúdo em
     * base64, assinado como qualquer outro corpo (decisão da frente B: sem
     * multipart). Devolve `attachment_id` e `url`.
     *
     * @param  string  $content  bytes do PDF
     */
    public function uploadDocument(int $hubBookId, string $type, string $filename, string $content, ?string $version = null, ?int $hubChapterId = null): array
    {
        if (! in_array($type, ['book_sheet', 'chapter_sheet'], true)) {
            throw new RuntimeException("Tipo de documento desconhecido: {$type}");
        }

        return $this->send('post', '/documentos', [
            'hub_book_id' => $hubBookId,
            'hub_chapter_id' => $hubChapterId,
            'type' => $type,
            'filename' => $filename,
            'version' => $version,
            'content_base64' => base64_encode($content),
        ]);
    }

    private function send(string $method, string $path, array $body): array
    {
        if ($this->url === '' || $this->secret === '') {
            throw new RuntimeException('Site não configurado: defina CONEXAO_DOI_WP_URL e CONEXAO_DOI_WP_SECRET no .env.');
        }

        // A assinatura cobre exatamente os bytes enviados: serializa uma vez e manda como corpo bruto.
        $json = $method === 'get' ? '' : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;

        $request = Http::withHeaders([
            'X-Conexao-Timestamp' => $timestamp,
            'X-Conexao-Signature' => self::sign($timestamp, $json, $this->secret),
        ])->acceptJson()->timeout(60);

        if ($method !== 'get') {
            $request = $request->withBody($json, 'application/json');
        }

        $response = $request->{$method}($this->url.self::BASE_PATH.$path);

        if ($response->failed()) {
            throw new RuntimeException("O site respondeu HTTP {$response->status()}: ".mb_substr(trim($response->body()), 0, 300));
        }

        return (array) $response->json();
    }
}
