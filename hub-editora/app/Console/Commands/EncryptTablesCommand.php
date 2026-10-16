<?php

namespace App\Console\Commands;

use App\Models\SyncLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Garante que todas as tabelas do hub estão cifradas em repouso no MariaDB
 * (plugin file_key_management, ativado no servidor em 16/10/2026).
 * Roda a cada deploy, depois das migrações: tabela nova nasce sem cifra.
 *
 *   php artisan hub:encrypt-tables            cifra o que faltar
 *   php artisan hub:encrypt-tables --conferir só lista o que está sem cifra
 */
class EncryptTablesCommand extends Command
{
    protected $signature = 'hub:encrypt-tables {--conferir : Só lista as tabelas sem cifra}';

    protected $description = 'Cifra em repouso as tabelas do hub que ainda não estão cifradas (MariaDB)';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mariadb' && DB::connection()->getDriverName() !== 'mysql') {
            $this->line('Banco não é MariaDB; nada a fazer.');

            return self::SUCCESS;
        }

        $plugin = DB::selectOne("select plugin_status from information_schema.plugins where plugin_name = 'file_key_management'");

        if (($plugin->plugin_status ?? null) !== 'ACTIVE') {
            $this->warn('Plugin de chaves do MariaDB inativo: tabelas não podem ser cifradas.');

            return $this->option('conferir') ? self::FAILURE : self::SUCCESS;
        }

        $db = DB::connection()->getDatabaseName();
        $pending = collect(DB::select(
            "select t.table_name as name from information_schema.tables t
             left join information_schema.innodb_tablespaces_encryption e on e.name = concat(t.table_schema, '/', t.table_name)
             where t.table_schema = ? and t.engine = 'InnoDB' and coalesce(e.encryption_scheme, 0) = 0",
            [$db]
        ))->pluck('name');

        if ($pending->isEmpty()) {
            $this->info('Todas as tabelas estão cifradas.');

            return self::SUCCESS;
        }

        if ($this->option('conferir')) {
            $this->warn('Sem cifra: '.$pending->implode(', '));

            return self::FAILURE;
        }

        foreach ($pending as $table) {
            try {
                DB::statement("ALTER TABLE `{$table}` ENCRYPTED=YES ENCRYPTION_KEY_ID=1");
                $this->line("cifrada: {$table}");
            } catch (Throwable $e) {
                $this->error("{$table}: ".$e->getMessage());

                return self::FAILURE;
            }
        }

        SyncLog::record(null, SyncLog::MANUAL, 'db.encrypted', null, 'Tabelas cifradas em repouso: '.$pending->implode(', '));

        return self::SUCCESS;
    }
}
