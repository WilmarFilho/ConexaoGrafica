<?php

namespace App\Console\Commands;

use App\Models\SyncLog;
use App\Support\BackupCrypto;
use App\Support\SecurityAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Lista, confere e baixa backups do armazenamento externo.
 *
 *   php artisan hub:backup-restore --listar
 *   php artisan hub:backup-restore --conferir           confere o mais recente (decifra e valida, sem gravar);
 *                                                       roda toda semana pelo agendador e alerta se falhar
 *   php artisan hub:backup-restore --saida=/caminho.sql baixa e decifra o mais recente para um arquivo .sql
 *   php artisan hub:backup-restore db/hub-20261016-024000.sql.gz.enc --saida=...
 *
 * A restauração em si (mysql < arquivo.sql) é manual, de propósito.
 */
class BackupRestoreCommand extends Command
{
    protected $signature = 'hub:backup-restore {arquivo? : Caminho no armazenamento; padrão: o mais recente}
        {--listar : Lista os backups disponíveis}
        {--conferir : Só confere se decifra e está íntegro}
        {--saida= : Arquivo .sql de destino}';

    protected $description = 'Confere ou baixa (decifrado) um backup do armazenamento externo';

    public function handle(): int
    {
        if ($this->option('conferir') && (blank(config('hub.backup.key')) || blank(config('filesystems.disks.backup.bucket')))) {
            $this->warn('Backup externo não configurado (BACKUP_ENCRYPTION_KEY e BACKUP_S3_*); nada a conferir.');

            return self::SUCCESS;
        }

        $disk = Storage::disk('backup');

        try {
            $files = collect($disk->files('db'))->filter(fn ($f) => str_ends_with($f, '.sql.gz.enc'))->sort()->values();
        } catch (Throwable $e) {
            // Credencial errada, bucket inexistente, provedor fora do ar...
            return $this->falhou('armazenamento inacessível: '.mb_substr($e->getMessage(), 0, 300));
        }

        if ($this->option('listar')) {
            foreach ($files as $f) {
                $this->line($f.'  '.number_format($disk->size($f) / 1024, 0, ',', '.').' KB');
            }
            $this->info($files->count().' backup(s).');

            return self::SUCCESS;
        }

        $name = $this->argument('arquivo') ?? $files->last();

        if (! $name || ! $disk->exists($name)) {
            return $this->falhou('Backup não encontrado.');
        }

        try {
            $sql = gzdecode(BackupCrypto::decrypt($disk->get($name), (string) config('hub.backup.key')));

            if ($sql === false || ! str_contains($sql, 'CREATE TABLE `orders`')) {
                throw new \RuntimeException('conteúdo decifrado não parece um dump do hub.');
            }

            $tabelas = preg_match_all('/^CREATE TABLE `/m', $sql);

            if ($this->option('conferir')) {
                SyncLog::record(null, SyncLog::MANUAL, 'backup.checked', null, "Backup {$name} conferido: decifra e contém {$tabelas} tabela(s)");
                $this->info("OK: {$name} decifra e contém {$tabelas} tabela(s).");

                return self::SUCCESS;
            }

            $saida = (string) $this->option('saida');

            if ($saida === '') {
                $this->error('Informe --saida=/caminho/arquivo.sql (ou use --conferir).');

                return self::FAILURE;
            }

            file_put_contents($saida, $sql);
            @chmod($saida, 0600);
            $this->info("Backup decifrado em {$saida} ({$tabelas} tabelas). Restaurar: mysql nome_do_banco < {$saida}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            return $this->falhou('Falha: '.mb_substr($e->getMessage(), 0, 300));
        }
    }

    /** Na conferência (agendada), a falha vai para a Auditoria e alerta os administradores. */
    private function falhou(string $msg): int
    {
        if ($this->option('conferir')) {
            $msg = 'Conferência do backup externo falhou: '.$msg;
            SyncLog::record(null, SyncLog::MANUAL, 'backup.failed', null, $msg, [], 'error');
            SecurityAudit::alert($msg);
        }

        $this->error($msg);

        return self::FAILURE;
    }
}
