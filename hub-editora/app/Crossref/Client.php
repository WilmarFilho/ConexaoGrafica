<?php

namespace App\Crossref;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTPS da Crossref (depósito por POST multipart e leitura do relatório
 * de processamento). Credenciais só de config('hub.crossref.*'); `env=test`
 * fala com test.crossref.org (homologação), `production` com doi.crossref.org.
 *
 * Nunca interpreta HTTP 200 como DOI registrado: o depósito aceito vira `sent`;
 * `registered` só vem do relatório (result()).
 */
class Client
{
    public const TEST_HOST = 'https://test.crossref.org';

    public const PRODUCTION_HOST = 'https://doi.crossref.org';

    public function __construct(
        private readonly string $env,
        private readonly string $login,
        private readonly string $password,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) (config('hub.crossref.env') ?: 'test'),
            (string) config('hub.crossref.login'),
            (string) config('hub.crossref.password'),
        );
    }

    public static function isConfigured(): bool
    {
        return filled(config('hub.crossref.login')) && filled(config('hub.crossref.password'));
    }

    public function isProduction(): bool
    {
        return $this->env === 'production';
    }

    public function host(): string
    {
        return $this->isProduction() ? self::PRODUCTION_HOST : self::TEST_HOST;
    }

    public function depositUrl(): string
    {
        return $this->host().'/servlet/deposit';
    }

    public function resultUrl(): string
    {
        return $this->host().'/servlet/submissionDownload';
    }

    /**
     * Envia o XML (operation=doMDUpload). Lança ConnectionException em timeout /
     * falha de rede (depois de 2 novas tentativas); respostas HTTP, mesmo 4xx/5xx,
     * voltam como DepositResponse recusado.
     *
     * @throws ConnectionException
     * @throws CrossrefException sem credenciais
     */
    public function deposit(string $xml, string $filename): DepositResponse
    {
        $this->assertCredentials();

        $response = $this->http(90)
            ->asMultipart()
            ->attach('fname', $xml, $filename, ['Content-Type' => 'application/xml'])
            ->post($this->depositUrl(), [
                'operation' => 'doMDUpload',
                'login_id' => $this->login,
                'login_passwd' => $this->password,
            ]);

        return DepositResponse::fromHttp($response->status(), $response->body());
    }

    /**
     * Relatório de processamento do lote (type=result). A Crossref exige as
     * credenciais na query string; por isso a URL nunca vai para log.
     *
     * @throws ConnectionException
     * @throws CrossrefException sem credenciais
     */
    public function result(string $batchId, string $filename): BatchResult
    {
        $this->assertCredentials();

        $response = $this->http(60)->get($this->resultUrl(), [
            'usr' => $this->login,
            'pwd' => $this->password,
            'doi_batch_id' => $batchId,
            'file_name' => $filename,
            'type' => 'result',
        ]);

        return BatchResult::fromHttp($response->status(), $response->body());
    }

    private function http(int $timeout): PendingRequest
    {
        return Http::timeout($timeout)
            ->connectTimeout(15)
            ->withUserAgent('Hub Editora / Conexão DOI')
            // Só repete em falha de rede; uma resposta HTTP (mesmo de erro) é definitiva.
            ->retry(3, 2000, fn (\Throwable $e) => $e instanceof ConnectionException, throw: false);
    }

    private function assertCredentials(): void
    {
        if ($this->login === '' || $this->password === '') {
            throw new CrossrefException('Credenciais da Crossref não configuradas (CROSSREF_LOGIN e CROSSREF_PASSWORD no .env).');
        }
    }
}
