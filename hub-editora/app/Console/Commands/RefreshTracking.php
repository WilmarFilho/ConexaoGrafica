<?php

namespace App\Console\Commands;

use App\Integrations\MelhorEnvio\MelhorEnvioClient;
use App\Integrations\MelhorEnvio\ShipmentService;
use App\Models\Shipment;
use Illuminate\Console\Command;
use Throwable;

class RefreshTracking extends Command
{
    protected $signature = 'hub:tracking {--limit=200 : Máximo de envios por execução}';

    protected $description = 'Atualiza rastreio e situação dos envios abertos no Melhor Envio';

    public function handle(ShipmentService $service): int
    {
        if (! MelhorEnvioClient::isConfigured()) {
            $this->warn('Melhor Envio não configurado; nada a fazer.');

            return self::SUCCESS;
        }

        $open = Shipment::query()
            ->whereNotNull('melhor_envio_id')
            ->whereIn('status', [Shipment::PURCHASED, Shipment::LABEL_GENERATED, Shipment::SHIPPED])
            ->orderBy('updated_at')
            ->limit((int) $this->option('limit'))
            ->get();

        $ok = 0;
        foreach ($open as $shipment) {
            try {
                $service->refreshTracking($shipment);
                $ok++;
            } catch (Throwable $e) {
                $this->error("Envio {$shipment->id}: {$e->getMessage()}");
            }
        }

        $this->info("{$ok}/{$open->count()} envios atualizados.");

        return self::SUCCESS;
    }
}
