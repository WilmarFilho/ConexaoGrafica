<?php

namespace App\Console\Commands;

use App\Models\SyncLog;
use App\Support\BackupCrypto;
use App\Support\SecurityAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Backup diário do banco do hub para fora do servidor (Amazon DPP 2.7):
 * mysqldump → gzip → AES-256-GCM → armazenamento S3 em outra região.
 * Guarda os últimos N dias (BACKUP_RETENTION_DAYS) e registra tudo na Auditoria.
 *
 *   php artisan hub:backup               faz o backup agora
 *   php artisan hub:backup --gerar-chave mostra uma chave nova (copiar para o .env e guardar fora do servidor)
 */
class BackupCommand extends Command
{
    protected $signature = 'hub:backup {--gerar-chave : Só mostra uma chave de cifra nova}';

    protected $description = 'Backup do banco, cifrado, para o armazenamento externo';

    public function handle(): int
    {
        if ($this->option('gerar-chave')) {
            $this->line(BackupCrypto::generateKey());
            $this->comment('Coloque em BACKUP_ENCRYPTION_KEY no .env e guarde uma cópia fora do servidor (cofre de senhas). Sem ela os backups não podem ser restaurados.');

            return self::SUCCESS;
        }

        $key = (string) config('hub.backup.key');

        if ($key === '' || blank(config('filesystems.disks.backup.bucket'))) {
            $this->warn('Backup externo não configurado (BACKUP_ENCRYPTION_KEY e BACKUP_S3_*); nada a fazer.');

            return self::SUCCESS;
        }

        try {
            $dump = $this->dump();
            $blob = BackupCrypto::encrypt(gzencode($dump, 9), $key);
            $name = 'db/hub-'.now()->format('Ymd-His').'.sql.gz.enc';

            $disk = Storage::disk('backup');
            $disk->put($name, $blob);

            if ($disk->size($name) !== strlen($blob)) {
                throw new \RuntimeException('Tamanho do arquivo enviado não confere.');
            }

            $removed = $this->prune();

            SyncLog::record(null, SyncLog::OUT, 'backup.sent', null,
                sprintf('Backup %s enviado (%s KB cifrados)%s', basename($name), number_format(strlen($blob) / 1024, 0, ',', '.'),
                    $removed ? ', '.$removed.' antigo(s) removido(s)' : ''),
                ['file' => $name, 'bytes' => strlen($blob)]);

            $this->info("Backup enviado: {$name}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $msg = 'Backup externo falhou: '.mb_substr($e->getMessage(), 0, 300);
            SyncLog::record(null, SyncLog::OUT, 'backup.failed', null, $msg, [], 'error');
            SecurityAudit::alert($msg);
            $this->error($msg);

            return self::FAILURE;
        }
    }

    /** mysqldump do banco do hub (consistente, sem travar as tabelas). */
    private function dump(): string
    {
        $db = config('database.connections.'.config('database.default'));

        $result = Process::timeout(600)
            ->env(['MYSQL_PWD' => (string) ($db['password'] ?? '')])
            ->run([
                'mysqldump',
                '--host='.($db['host'] ?? '127.0.0.1'),
                '--port='.($db['port'] ?? 3306),
                '--user='.($db['username'] ?? ''),
                '--single-transaction', '--quick', '--no-tablespaces', '--routines', '--default-character-set=utf8mb4',
                (string) ($db['database'] ?? ''),
            ]);

        if (! $result->successful() || ! str_contains($result->output(), 'CREATE TABLE')) {
            throw new \RuntimeException('mysqldump: '.mb_substr(trim($result->errorOutput()) ?: 'saída vazia', 0, 200));
        }

        return $result->output();
    }

    /** Apaga do armazenamento externo os backups mais velhos que a retenção. */
    private function prune(): int
    {
        $limite = now()->subDays(max(1, (int) config('hub.backup.retention_days', 30)));
        $disk = Storage::disk('backup');
        $removed = 0;

        foreach ($disk->files('db') as $file) {
            if (preg_match('/hub-(\d{8}-\d{6})\.sql\.gz\.enc$/', $file, $m)
                && Carbon::createFromFormat('Ymd-His', $m[1])->lt($limite)) {
                $disk->delete($file);
                $removed++;
            }
        }

        return $removed;
    }
}
