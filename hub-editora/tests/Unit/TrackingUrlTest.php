<?php

namespace Tests\Unit;

use App\Models\Shipment;
use PHPUnit\Framework\TestCase;

class TrackingUrlTest extends TestCase
{
    public function test_monta_link_do_melhor_rastreio(): void
    {
        $this->assertSame('https://www.melhorrastreio.com.br/rastreio/AP502354781BR', Shipment::trackingUrl(' ap502354781br '));
    }

    public function test_sem_codigo_ou_codigo_estranho_nao_vira_link(): void
    {
        $this->assertNull(Shipment::trackingUrl(null));
        $this->assertNull(Shipment::trackingUrl(''));
        $this->assertNull(Shipment::trackingUrl('AP50/../x'));
    }
}
