<?php

namespace App\Support;

use App\Integrations\Bling\BlingClient;
use App\Integrations\MelhorEnvio\MelhorEnvioClient;
use App\Models\Channel;
use App\Models\SyncLog;
use App\Models\User;
use App\Notifications\IntegrationDown;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Avisos de integração caída: e-mail aos usuários (com trava de 6 h por canal)
 * e a lista de pendências que a Visão geral mostra no topo.
 */
class IntegrationAlerts
{
    public const THROTTLE_HOURS = 6;

    /**
     * Falhas consecutivas antes de avisar. As APIs (Bling em especial) devolvem
     * erros passageiros; com varredura a cada 15 min, 3 seguidas = ~45 min fora.
     */
    public const CONSECUTIVE_FAILURES = 3;

    /**
     * Chamado por quem detectou a queda (refresh do Bling, varreduras).
     * $immediate = true pula a contagem (ex.: token revogado, que não volta sozinho).
     */
    public static function down(string $slug, string $reason, bool $immediate = false): void
    {
        $channel = Channel::bySlug($slug);
        $name = $channel?->name ?? $slug;
        $key = "hub.alert.down.{$slug}";

        if (! $immediate) {
            $failures = (int) Cache::get("hub.alert.failures.{$slug}", 0) + 1;
            Cache::put("hub.alert.failures.{$slug}", $failures, now()->addHours(24));
            if ($failures < self::CONSECUTIVE_FAILURES) {
                return; // pode ser passageiro; espera a próxima varredura
            }
            $reason = "{$failures} falhas seguidas. Última: {$reason}";
        }

        if (Cache::has($key)) {
            return; // já avisado nas últimas horas
        }
        Cache::put($key, now()->toDateTimeString(), now()->addHours(self::THROTTLE_HOURS));

        $sent = 0;
        foreach (User::query()->whereNotNull('email')->get() as $user) {
            try {
                $user->notify(new IntegrationDown($name, mb_substr($reason, 0, 300)));
                $sent++;
            } catch (Throwable) {
                // e-mail indisponível não pode derrubar a sincronização
            }
        }

        SyncLog::record($channel, SyncLog::OUT, 'alert.sent', null, "Aviso de queda enviado a {$sent} usuário(s): ".mb_substr($reason, 0, 200), [], 'warning');
    }

    /** Libera um novo aviso quando o canal voltar (chamado após sync com sucesso). */
    public static function recovered(string $slug): void
    {
        Cache::forget("hub.alert.down.{$slug}");
        Cache::forget("hub.alert.failures.{$slug}");
    }

    /**
     * Pendências abertas agora, para o banner da Visão geral.
     *
     * @return array<int, array{channel:string, message:string, action:string}>
     */
    public static function openIssues(): array
    {
        $issues = [];
        // function + use (&$issues): arrow fn capturaria a lista por valor e nada seria acumulado.
        $add = function (string $channel, string $message, string $action = 'Abrir Integrações') use (&$issues): void {
            $issues[] = compact('channel', 'message', 'action');
        };

        if (! filled(config('hub.woocommerce.url')) || ! filled(config('hub.woocommerce.key'))) {
            $add('WooCommerce', 'sem credenciais: pedidos da loja não estão sendo importados');
        }
        if (! filled(config('hub.pagarme.secret_key'))) {
            $add('Pagar.me', 'sem chave secreta: pedidos das landing pages não estão sendo importados');
        }
        if (! MelhorEnvioClient::isConfigured()) {
            $add('Melhor Envio', 'sem token ou CEP de origem: não dá para cotar nem gerar etiquetas');
        }
        if (BlingClient::isConfigured() && ! BlingClient::isConnected()) {
            $add('Bling / Amazon', 'chaves salvas, mas a conta não está autorizada: pedidos da Amazon parados', 'Reconectar ao Bling');
        }

        foreach (Channel::query()->where('last_sync_status', 'error')->get() as $c) {
            $add($c->name, 'última sincronização falhou'.($c->last_sync_at ? ' '.$c->last_sync_at->diffForHumans() : '').': '.mb_substr((string) $c->last_sync_message, 0, 140));
        }

        return $issues;
    }
}
