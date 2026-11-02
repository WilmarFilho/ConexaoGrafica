<?php

namespace Tests\Feature;

use App\Support\Fuso;
use Carbon\CarbonImmutable;
use Filament\Support\Facades\FilamentTimezone;
use Tests\TestCase;

/** Banco em UTC, telas e recortes por dia no horário de Brasília. */
class FusoTest extends TestCase
{
    public function test_dia_de_brasilia_vira_intervalo_em_utc(): void
    {
        $this->assertSame('2026-11-02 03:00:00', Fuso::inicioDoDia('2026-11-02')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-03 02:59:59', Fuso::fimDoDia('2026-11-02')->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', Fuso::inicioDoDia('2026-11-02')->getTimezone()->getName());
    }

    public function test_data_gravada_em_utc_aparece_no_horario_de_brasilia(): void
    {
        $gravada = CarbonImmutable::parse('2026-11-03 01:30:00', 'UTC');

        $this->assertSame('02/11/2026 22:30', Fuso::formata($gravada));
        $this->assertSame('', Fuso::formata(null));
    }

    public function test_filament_e_agendador_usam_brasilia(): void
    {
        $this->assertSame('America/Sao_Paulo', FilamentTimezone::get());
        $this->assertSame('America/Sao_Paulo', config('app.schedule_timezone'));
        $this->assertSame('UTC', config('app.timezone'));
    }
}
