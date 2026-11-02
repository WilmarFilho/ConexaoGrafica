<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Horário de Brasília na tela. O banco e as integrações continuam em UTC
 * (app.timezone): só a exibição e os recortes por dia ("hoje", filtro De/Até)
 * passam pelo fuso da editora, senão tudo aparece 3 h adiantado.
 */
class Fuso
{
    public static function nome(): string
    {
        return (string) config('hub.fuso', 'America/Sao_Paulo');
    }

    public static function agora(): CarbonImmutable
    {
        return CarbonImmutable::now(self::nome());
    }

    /** Data gravada (UTC) no horário de Brasília. */
    public static function local(?CarbonInterface $data): ?CarbonImmutable
    {
        return $data ? CarbonImmutable::instance($data)->setTimezone(self::nome()) : null;
    }

    public static function formata(?CarbonInterface $data, string $formato = 'd/m/Y H:i'): string
    {
        return self::local($data)?->format($formato) ?? '';
    }

    /** 00:00 do dia em Brasília, em UTC, para comparar com o banco. */
    public static function inicioDoDia(CarbonInterface|string $dia): CarbonImmutable
    {
        return self::dia($dia)->startOfDay()->utc();
    }

    /** 23:59:59 do dia em Brasília, em UTC. */
    public static function fimDoDia(CarbonInterface|string $dia): CarbonImmutable
    {
        return self::dia($dia)->endOfDay()->utc();
    }

    /** Linhas cuja coluna cai naquele dia de Brasília. */
    public static function noDia(Builder $query, string $coluna, CarbonInterface|string $dia): Builder
    {
        return $query->whereBetween($coluna, [self::inicioDoDia($dia), self::fimDoDia($dia)]);
    }

    private static function dia(CarbonInterface|string $dia): CarbonImmutable
    {
        $texto = $dia instanceof CarbonInterface ? $dia->format('Y-m-d') : substr($dia, 0, 10);

        return CarbonImmutable::createFromFormat('Y-m-d', $texto, self::nome());
    }
}
