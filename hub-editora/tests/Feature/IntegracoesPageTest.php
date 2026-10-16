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
            ->fillForm(['amazon.client_id' => 'cid-123', 'amazon.client_secret' => 'segredo-xyz'])
            ->call('save')
            ->assertNotified('Integrações salvas');

        $this->assertSame('cid-123', HubSettings::stored()['amazon.client_id']);
        $this->assertSame('segredo-xyz', HubSettings::stored()['amazon.client_secret']);
        $this->assertSame('cid-123', config('hub.amazon.client_id'));

        // Segredo em branco no formulário = manter; o texto só muda se mudar.
        Livewire::test(Integracoes::class)
            ->fillForm(['amazon.client_id' => 'cid-123', 'amazon.client_secret' => ''])
            ->call('save')
            ->assertNotified('Nada mudou');

        $this->assertSame('segredo-xyz', Setting::where('key', 'amazon.client_secret')->first()->value);

        // Ao reabrir, o texto salvo aparece preenchido (caminho aninhado no estado).
        Livewire::test(Integracoes::class)->assertFormSet(['amazon.client_id' => 'cid-123']);
    }
}
