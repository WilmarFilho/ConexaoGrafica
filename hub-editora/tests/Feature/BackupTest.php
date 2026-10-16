<?php

namespace Tests\Feature;

use App\Models\SyncLog;
use App\Models\User;
use App\Notifications\SecurityAlert;
use App\Support\BackupCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private string $key;

    private string $dump = "-- MariaDB dump\nCREATE TABLE `orders` (`id` int);\nCREATE TABLE `users` (`id` int);\nINSERT INTO `orders` VALUES (1);\n";

    protected function setUp(): void
    {
        parent::setUp();
        $this->key = BackupCrypto::generateKey();
        config(['hub.backup.key' => $this->key, 'hub.backup.retention_days' => 30, 'filesystems.disks.backup.bucket' => 'hub-backups']);
        Storage::fake('backup');
        Notification::fake();
    }

    public function test_sends_an_encrypted_dump_and_prunes_old_ones(): void
    {
        Storage::disk('backup')->put('db/hub-20200101-024000.sql.gz.enc', 'velho');
        Process::fake(['*' => Process::result($this->dump)]);

        $this->artisan('hub:backup')->assertSuccessful();

        $files = Storage::disk('backup')->files('db');
        $this->assertCount(1, $files, 'o antigo sai, o novo fica');
        $blob = Storage::disk('backup')->get($files[0]);

        $this->assertStringNotContainsString('CREATE TABLE', $blob, 'conteúdo cifrado');
        $this->assertSame($this->dump, gzdecode(BackupCrypto::decrypt($blob, $this->key)));
        $this->assertStringContainsString('1 antigo(s) removido(s)', SyncLog::where('action', 'backup.sent')->first()->message);

        // A senha do banco vai por variável de ambiente, nunca na linha de comando.
        Process::assertRan(fn ($p) => is_array($p->command) && $p->command[0] === 'mysqldump'
            && ! collect($p->command)->contains(fn ($a) => str_starts_with($a, '--password')));
    }

    public function test_restore_check_detects_wrong_key_and_tampering(): void
    {
        Process::fake(['*' => Process::result($this->dump)]);
        $this->artisan('hub:backup')->assertSuccessful();
        $file = Storage::disk('backup')->files('db')[0];

        $this->artisan('hub:backup-restore --conferir')->expectsOutputToContain('2 tabela(s)')->assertSuccessful();
        $this->assertSame(1, SyncLog::where('action', 'backup.checked')->count());

        $saida = sys_get_temp_dir().'/hub-restore-'.uniqid().'.sql';
        $this->artisan('hub:backup-restore', ['--saida' => $saida])->assertSuccessful();
        $this->assertSame($this->dump, file_get_contents($saida));
        @unlink($saida);

        $blob = Storage::disk('backup')->get($file);
        Storage::disk('backup')->put($file, substr($blob, 0, -1).chr(ord(substr($blob, -1)) ^ 1));
        $this->artisan('hub:backup-restore --conferir')->assertFailed();

        config(['hub.backup.key' => BackupCrypto::generateKey()]);
        Storage::disk('backup')->put($file, $blob);
        $this->artisan('hub:backup-restore --conferir')->assertFailed();

        $this->assertSame(2, SyncLog::where('action', 'backup.failed')->count(), 'falha na conferência vai para a Auditoria');
    }

    public function test_failure_is_logged_and_alerts_admins(): void
    {
        $admin = User::factory()->create();
        Process::fake(['*' => Process::result('', 'Access denied for user', 2)]);

        $this->artisan('hub:backup')->assertFailed();

        $this->assertSame(1, SyncLog::where('action', 'backup.failed')->count());
        Notification::assertSentTo($admin, SecurityAlert::class);
        $this->assertSame([], Storage::disk('backup')->files('db'));
    }

    public function test_without_configuration_it_only_warns(): void
    {
        config(['hub.backup.key' => null]);
        $this->artisan('hub:backup')->expectsOutputToContain('não configurado')->assertSuccessful();
        $this->artisan('hub:backup --gerar-chave')->expectsOutputToContain('base64:')->assertSuccessful();
        $this->artisan('hub:backup-restore --conferir')->expectsOutputToContain('não configurado')->assertSuccessful();
    }

    public function test_weekly_check_alerts_when_storage_is_unreachable(): void
    {
        $admin = User::factory()->create();
        Storage::shouldReceive('disk')->with('backup')->andReturn(new class
        {
            public function files(): array
            {
                throw new \RuntimeException('InvalidAccessKeyId');
            }
        });

        $this->artisan('hub:backup-restore --conferir')->assertFailed();

        $this->assertStringContainsString('InvalidAccessKeyId', SyncLog::where('action', 'backup.failed')->first()->message);
        Notification::assertSentTo($admin, SecurityAlert::class);
    }

    public function test_weekly_check_alerts_when_there_is_no_backup(): void
    {
        $admin = User::factory()->create();

        $this->artisan('hub:backup-restore --conferir')->assertFailed();

        $this->assertSame(1, SyncLog::where('action', 'backup.failed')->count());
        Notification::assertSentTo($admin, SecurityAlert::class);
    }

    public function test_encrypt_tables_is_a_no_op_outside_mariadb(): void
    {
        $this->artisan('hub:encrypt-tables')->expectsOutputToContain('nada a fazer')->assertSuccessful();
    }

    public function test_security_headers_are_sent(): void
    {
        $r = $this->get('/privacidade');

        $r->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
        $this->assertStringContainsString("frame-ancestors 'self'", $r->headers->get('Content-Security-Policy'));
        $this->assertDoesNotMatchRegularExpression('/(^|[\s;])(https?:|\*)(;|\s|$)/', $r->headers->get('Content-Security-Policy'), 'sem origem curinga');
        $this->assertNotNull($r->headers->get('Permissions-Policy'));
    }
}
