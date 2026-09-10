<?php

namespace Tests\Feature;

use App\Filament\Pages\Integracoes;
use App\Models\Setting;
use App\Models\User;
use App\Support\HubSettings;
use Database\Seeders\ChannelSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IntegracoesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChannelSeeder::class);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_saves_dotted_keys_and_keeps_secrets_when_left_blank(): void
    {
        Livewire::test(Integracoes::class)
            ->fillForm(['bling.client_id' => 'cid-123', 'bling.client_secret' => 'segredo-xyz'])
            ->call('save')
            ->assertNotified('Integrações salvas');

        $this->assertSame('cid-123', HubSettings::stored()['bling.client_id']);
        $this->assertSame('segredo-xyz', HubSettings::stored()['bling.client_secret']);
        $this->assertSame('cid-123', config('hub.bling.client_id'));

        // Segredo em branco no formulário = manter; o texto só muda se mudar.
        Livewire::test(Integracoes::class)
            ->fillForm(['bling.client_id' => 'cid-123', 'bling.client_secret' => ''])
            ->call('save')
            ->assertNotified('Nada mudou');

        $this->assertSame('segredo-xyz', Setting::where('key', 'bling.client_secret')->first()->value);

        // Ao reabrir, o texto salvo aparece preenchido (caminho aninhado no estado).
        Livewire::test(Integracoes::class)->assertFormSet(['bling.client_id' => 'cid-123']);
    }
}
