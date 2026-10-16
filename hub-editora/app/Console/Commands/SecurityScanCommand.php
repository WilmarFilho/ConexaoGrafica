<?php

namespace App\Console\Commands;

use App\Models\SyncLog;
use App\Support\SecurityAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Varredura mensal de vulnerabilidades (Amazon DPP 2.7): confere todas as
 * dependências instaladas contra a base de vulnerabilidades conhecidas
 * (composer audit). O resultado vai para a Auditoria; se houver achado,
 * os administradores recebem alerta com o prazo de correção.
 * A mesma verificação roda antes de toda publicação (deploy/check.sh).
 */
class SecurityScanCommand extends Command
{
    protected $signature = 'hub:security-scan';

    protected $description = 'Varre as dependências em busca de vulnerabilidades conhecidas e registra na Auditoria';

    public function handle(): int
    {
        $composer = (string) config('hub.security.composer_bin');

        $result = Process::path(base_path())->timeout(300)->run([
            PHP_BINARY, '-d', 'allow_url_fopen=1', $composer, 'audit', '--locked', '--no-dev', '--format=json',
        ]);

        $json = json_decode($result->output(), true);

        if (! is_array($json)) {
            $msg = 'Varredura não concluída: '.mb_substr(trim($result->errorOutput() ?: $result->output()), 0, 300);
            SyncLog::record(null, SyncLog::MANUAL, 'security.scan', null, $msg, [], 'error');
            SecurityAudit::alert($msg);
            $this->error($msg);

            return self::FAILURE;
        }

        $achados = [];
        foreach ($json['advisories'] ?? [] as $pacote => $lista) {
            foreach ((array) $lista as $a) {
                $achados[] = $pacote.' · '.($a['severity'] ?? 'sem severidade').' · '.($a['cve'] ?? $a['advisoryId'] ?? '').' · '.mb_substr((string) ($a['title'] ?? ''), 0, 120);
            }
        }

        if ($achados) {
            $msg = count($achados).' vulnerabilidade(s) nas dependências. Prazos: crítica em até 7 dias, alta em até 30 dias. '.implode(' | ', $achados);
            SyncLog::record(null, SyncLog::MANUAL, 'security.scan', null, mb_substr($msg, 0, 1000), ['achados' => $achados], 'warning');
            SecurityAudit::alert(mb_substr($msg, 0, 1000));
            $this->warn($msg);

            return self::SUCCESS;
        }

        SyncLog::record(null, SyncLog::MANUAL, 'security.scan', null, 'Nenhuma vulnerabilidade conhecida nas dependências');
        $this->info('Nenhuma vulnerabilidade conhecida nas dependências.');

        return self::SUCCESS;
    }
}
