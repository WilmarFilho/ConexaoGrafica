<?php

namespace App\Jobs;

use App\Integrations\PagarMe\ImportOrders as PagarMeImport;
use App\Integrations\WooCommerce\ImportOrders as WooImport;
use App\Models\Channel;
use App\Models\SyncLog;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Reimporta um pedido a partir do canal, disparado por webhook.
 *
 * O webhook só diz "o pedido X mudou"; o conteúdo é buscado de novo na API
 * do canal com a nossa credencial. Assim um payload forjado ou truncado
 * nunca vira dado no hub, e o mesmo caminho da varredura é reaproveitado.
 */
class ImportChannelOrder implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var int[] segundos entre tentativas */
    public array $backoff = [30, 120, 600];

    public function __construct(
        public string $channel,
        public string $externalId,
        public ?string $event = null,
    ) {}

    /** Rajadas do mesmo pedido (created + updated + paid) viram uma importação só. */
    public function uniqueId(): string
    {
        return $this->channel.':'.$this->externalId;
    }

    public int $uniqueFor = 60;

    public function handle(): void
    {
        try {
            match ($this->channel) {
                Channel::WOOCOMMERCE => WooImport::make()->one((int) $this->externalId),
                Channel::PAGARME => PagarMeImport::make()->one($this->externalId),
                default => null,
            };
        } catch (Throwable $e) {
            SyncLog::record(
                Channel::bySlug($this->channel),
                'in',
                'webhook.failed',
                null,
                "{$this->externalId}: {$e->getMessage()}",
                ['event' => $this->event],
                'error',
            );

            throw $e;
        }
    }
}
