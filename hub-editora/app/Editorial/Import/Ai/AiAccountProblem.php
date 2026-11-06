<?php

namespace App\Editorial\Import\Ai;

use App\Models\Import;
use App\Models\SyncLog;
use App\Models\User;
use App\Notifications\ImportBlocked;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Falhas da conta da IA que não se resolvem tentando de novo: saldo de créditos
 * acabou ou a chave foi recusada. A mensagem técnica do provedor (JSON em inglês)
 * vira uma instrução em português, e os administradores e quem enviou o PDF
 * recebem um e-mail (no máximo um a cada 6 h por tipo de problema).
 */
class AiAccountProblem
{
    public const CREDIT = 'credit';

    public const KEY = 'key';

    public const THROTTLE_HOURS = 6;

    private const CREDIT_HINTS = ['credit balance is too low', 'insufficient_quota', 'exceeded your current quota', 'purchase credits'];

    private const KEY_HINTS = ['invalid x-api-key', 'invalid_api_key', 'incorrect api key', 'authentication_error', 'permission_error', 'api_key não configurada', 'chave da ia não configurada'];

    /** CREDIT, KEY ou null (outro tipo de falha). Olha a exceção e as anteriores a ela. */
    public static function detect(Throwable $e): ?string
    {
        for ($cur = $e; $cur !== null; $cur = $cur->getPrevious()) {
            $text = mb_strtolower(get_class($cur).' '.$cur->getMessage());
            foreach (self::CREDIT_HINTS as $hint) {
                if (str_contains($text, $hint)) {
                    return self::CREDIT;
                }
            }
            if (str_ends_with(get_class($cur), 'AuthenticationException') || str_ends_with(get_class($cur), 'PermissionDeniedException')) {
                return self::KEY;
            }
            foreach (self::KEY_HINTS as $hint) {
                if (str_contains($text, $hint)) {
                    return self::KEY;
                }
            }
        }

        return null;
    }

    public static function message(string $kind): string
    {
        [$name, $console, $var] = config('hub.ai.provider') === 'anthropic'
            ? ['Anthropic', 'console.anthropic.com', 'ANTHROPIC_API_KEY']
            : ['OpenAI', 'platform.openai.com', 'OPENAI_API_KEY'];

        return $kind === self::CREDIT
            ? "Acabou o saldo de créditos da IA ({$name}). Recarregue em {$console} → Billing e clique em Reprocessar: a importação continua de onde parou, sem pagar de novo o que já foi feito."
            : "A chave da IA ({$name}) foi recusada: inválida, expirada, sem permissão ou não configurada. Troque {$var} no .env do servidor e clique em Reprocessar: a importação continua de onde parou.";
    }

    /** E-mail aos administradores e a quem enviou o PDF; trava de 6 h por tipo de problema. */
    public static function notify(Import $import, string $kind): int
    {
        if (! Cache::add("hub.alert.ai.{$kind}", now()->toDateTimeString(), now()->addHours(self::THROTTLE_HOURS))) {
            return 0; // já avisado nas últimas horas
        }

        $users = User::query()->whereNotNull('email')
            ->where(fn ($q) => $q->where('role', User::ROLE_ADMIN)->when($import->created_by, fn ($q) => $q->orWhere('id', $import->created_by)))
            ->get();

        $sent = 0;
        foreach ($users as $user) {
            try {
                $user->notify(new ImportBlocked($import, $kind, self::message($kind)));
                $sent++;
            } catch (Throwable) {
                // e-mail indisponível não pode esconder o erro da importação
            }
        }

        SyncLog::record(null, SyncLog::OUT, 'alert.sent', $import,
            sprintf('Aviso de importação parada (%s) enviado a %d usuário(s)', $kind === self::CREDIT ? 'sem saldo da IA' : 'chave da IA recusada', $sent), ['kind' => $kind], 'warning');

        return $sent;
    }
}
