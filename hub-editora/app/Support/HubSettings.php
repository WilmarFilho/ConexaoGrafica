<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Chaves de integração: o que está no banco (tela Integrações) vence o .env.
 * Os valores são aplicados por cima de config('hub.*') no boot, então o resto
 * do sistema continua lendo config() sem saber de onde veio.
 */
class HubSettings
{
    private const CACHE_KEY = 'hub.settings.v1';

    /**
     * Catálogo das chaves editáveis: rótulo, se é segredo (mascarado na tela)
     * e o caminho equivalente em config('hub.*').
     *
     * @return array<string, array{label:string, secret:bool, config:string, help?:string}>
     */
    public static function definitions(): array
    {
        return [
            'woocommerce.url' => ['label' => 'URL da loja', 'secret' => false, 'config' => 'hub.woocommerce.url', 'help' => 'Ex.: https://novo.pubcon.com.br'],
            'woocommerce.key' => ['label' => 'Consumer key', 'secret' => true, 'config' => 'hub.woocommerce.key'],
            'woocommerce.secret' => ['label' => 'Consumer secret', 'secret' => true, 'config' => 'hub.woocommerce.secret'],
            'woocommerce.webhook_secret' => ['label' => 'Segredo dos webhooks', 'secret' => true, 'config' => 'hub.woocommerce.webhook_secret'],

            'pagarme.secret_key' => ['label' => 'Chave secreta (sk_)', 'secret' => true, 'config' => 'hub.pagarme.secret_key'],
            'pagarme.webhook_secret' => ['label' => 'Senha do webhook (Basic)', 'secret' => true, 'config' => 'hub.pagarme.webhook_secret'],

            'melhor_envio.token' => ['label' => 'Token de acesso', 'secret' => true, 'config' => 'hub.melhor_envio.token'],
            'melhor_envio.sandbox' => ['label' => 'Ambiente de testes (sandbox)', 'secret' => false, 'config' => 'hub.melhor_envio.sandbox'],
            'melhor_envio.from.name' => ['label' => 'Remetente', 'secret' => false, 'config' => 'hub.melhor_envio.from.name'],
            'melhor_envio.from.company_document' => ['label' => 'CNPJ', 'secret' => false, 'config' => 'hub.melhor_envio.from.company_document'],
            'melhor_envio.from.document' => ['label' => 'CPF (se pessoa física)', 'secret' => false, 'config' => 'hub.melhor_envio.from.document'],
            'melhor_envio.from.phone' => ['label' => 'Telefone', 'secret' => false, 'config' => 'hub.melhor_envio.from.phone'],
            'melhor_envio.from.email' => ['label' => 'E-mail', 'secret' => false, 'config' => 'hub.melhor_envio.from.email'],
            'melhor_envio.from.address' => ['label' => 'Rua', 'secret' => false, 'config' => 'hub.melhor_envio.from.address'],
            'melhor_envio.from.number' => ['label' => 'Número', 'secret' => false, 'config' => 'hub.melhor_envio.from.number'],
            'melhor_envio.from.complement' => ['label' => 'Complemento', 'secret' => false, 'config' => 'hub.melhor_envio.from.complement'],
            'melhor_envio.from.district' => ['label' => 'Bairro', 'secret' => false, 'config' => 'hub.melhor_envio.from.district'],
            'melhor_envio.from.city' => ['label' => 'Cidade', 'secret' => false, 'config' => 'hub.melhor_envio.from.city'],
            'melhor_envio.from.state' => ['label' => 'UF', 'secret' => false, 'config' => 'hub.melhor_envio.from.state'],
            'melhor_envio.from.postal_code' => ['label' => 'CEP de origem', 'secret' => false, 'config' => 'hub.melhor_envio.from.postal_code'],

            'bling.client_id' => ['label' => 'Client ID', 'secret' => false, 'config' => 'hub.bling.client_id'],
            'bling.client_secret' => ['label' => 'Client secret', 'secret' => true, 'config' => 'hub.bling.client_secret'],
        ];
    }

    /** Valores gravados no banco (já descriptografados), indexados pela chave. */
    public static function stored(): array
    {
        // Roda no boot da aplicação: sem banco, sem cache ou sem a tabela
        // (instalação nova, composer install, testes) tem que falhar em silêncio.
        try {
            return Cache::remember(self::CACHE_KEY, 300, function () {
                if (! Schema::hasTable('settings')) {
                    return [];
                }

                $out = [];
                foreach (Setting::query()->get() as $row) {
                    try {
                        $v = $row->value; // descriptografa; uma linha corrompida não derruba as outras
                    } catch (Throwable) {
                        continue;
                    }
                    if ($v !== null && $v !== '') {
                        $out[$row->key] = $v;
                    }
                }

                return $out;
            });
        } catch (Throwable) {
            return [];
        }
    }

    /** Valor efetivo (banco → .env), como o sistema o enxerga. */
    public static function effective(string $key): mixed
    {
        $def = self::definitions()[$key] ?? null;

        return $def ? config($def['config']) : null;
    }

    /** Onde o valor efetivo veio: 'banco', 'env' ou null quando vazio. */
    public static function source(string $key): ?string
    {
        if (array_key_exists($key, self::stored())) {
            return 'banco';
        }

        return filled(self::effective($key)) ? 'env' : null;
    }

    /** Sobrepõe config('hub.*') com o que está no banco. Chamado no boot. */
    public static function applyToConfig(): void
    {
        $defs = self::definitions();

        foreach (self::stored() as $key => $value) {
            if (! isset($defs[$key])) {
                continue;
            }
            if ($key === 'melhor_envio.sandbox') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                config(['hub.melhor_envio.base_url' => $value
                    ? 'https://sandbox.melhorenvio.com.br/api/v2'
                    : 'https://melhorenvio.com.br/api/v2']);
            }
            config([$defs[$key]['config'] => $value]);
        }
    }

    /** Grava (ou apaga, quando null) e reaplica na hora. */
    public static function set(string $key, ?string $value, ?int $userId = null): void
    {
        if ($value === null || $value === '') {
            Setting::query()->where('key', $key)->delete();
        } else {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $userId]);
        }

        Cache::forget(self::CACHE_KEY);
        self::applyToConfig();
    }

    /** "••••••••1a2b" para mostrar que existe sem revelar. */
    public static function mask(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }
        $v = (string) $value;

        return str_repeat('•', 8).mb_substr($v, -4);
    }
}
