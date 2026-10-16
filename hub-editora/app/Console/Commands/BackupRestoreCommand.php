<?php

namespace App\Console\Commands;

use App\Models\SyncLog;
use App\Support\BackupCrypto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Lista, confere e baixa backups do armazenamento externo.
 *
 *   php artisan hub:backup-restore --listar
 *   php artisan hub:backup-restore --conferir           confere o mais recente (decifra e valida, sem gravar)
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
        $disk = Storage::disk('backup');
        $files = collect($disk->files('db'))->filter(fn ($f) => str_ends_with($f, '.sql.gz.enc'))->sort()->values();

        if ($this->option('listar')) {
            foreach ($files as $f) {
                $this->line($f.'  '.number_format($disk->size($f) / 1024, 0, ',', '.').' KB');
            }
            $this->info($files->count().' backup(s).');

            return self::SUCCESS;
        }

        $name = $this->argument('arquivo') ?? $files->last();

        if (! $name || ! $disk->exists($name)) {
            $this->error('Backup não encontrado.');

            return self::FAILURE;
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
            $this->error('Falha: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
