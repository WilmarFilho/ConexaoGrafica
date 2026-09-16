<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\SyncLog;
use App\Models\User;
use App\Notifications\SecurityDigest;
use Illuminate\Console\Command;

class SecurityDigestCommand extends Command
{
    protected $signature = 'hub:security-digest {--days=15 : Janela em dias}';

    protected $description = 'Envia aos usuários o resumo quinzenal da auditoria (revisão de segurança)';

    public function handle(): int
    {
        $since = now()->subDays((int) $this->option('days'))->startOfDay();
        $period = $since->format('d/m').' a '.now()->format('d/m/Y');
        $logs = SyncLog::query()->where('created_at', '>=', $since);

        $count = fn (string $action) => (clone $logs)->where('action', $action)->count();

        $stats = [
            'Logins' => $count('auth.login'),
            'Tentativas de login falhas' => $count('auth.failed'),
            'Usuários criados/alterados' => User::where('updated_at', '>=', $since)->count(),
            'Integrações alteradas ou testadas' => $count('settings.changed') + $count('settings.tested'),
            'Etapas alteradas manualmente' => $count('status.changed'),
            'Erros de integração' => (clone $logs)->where('level', 'error')->count(),
            'Alertas enviados' => $count('alert.sent'),
            'Dados pessoais apagados (pedidos)' => $count('pii.purged'),
        ];

        $highlights = [];
        if ($stats['Tentativas de login falhas'] >= 10) {
            $highlights[] = $stats['Tentativas de login falhas'].' tentativas de login falhas no período.';
        }
        foreach (Channel::query()->where('last_sync_status', 'error')->get() as $c) {
            $highlights[] = "Canal {$c->name} está com erro de sincronização.";
        }
        $failedIps = (clone $logs)->where('action', 'auth.failed')->get()->pluck('context.ip')->filter()->countBy()->sortDesc()->take(3);
        foreach ($failedIps as $ip => $n) {
            if ($n >= 5) {
                $highlights[] = "IP {$ip} com {$n} falhas de login.";
            }
        }

        $users = User::query()->whereNotNull('email')->get();
        foreach ($users as $user) {
            $user->notify(new SecurityDigest($period, $stats, $highlights));
        }

        SyncLog::record(null, SyncLog::OUT, 'security.digest', null, "Resumo de segurança ({$period}) enviado a {$users->count()} usuário(s)");
        $this->info("Resumo enviado a {$users->count()} usuário(s).");

        return self::SUCCESS;
    }
}
