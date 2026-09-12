<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use App\Notifications\IntegrationDown;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class UsersResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Notification::fake();
    }

    public function test_creating_a_user_sets_a_random_password_and_emails_the_reset_link(): void
    {
        Livewire::test(ListUsers::class)
            ->callAction('create', data: ['name' => 'Comercial', 'email' => 'comercial@exemplo.com'])
            ->assertHasNoActionErrors();

        $u = User::where('email', 'comercial@exemplo.com')->firstOrFail();
        $this->assertNotEmpty($u->password);
        Notification::assertSentTo($u, \Filament\Auth\Notifications\ResetPassword::class);
        Notification::assertNotSentTo($u, IntegrationDown::class);
    }

    public function test_cannot_delete_yourself_but_can_resend_the_link(): void
    {
        $me = auth()->user();
        $other = User::factory()->create();

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('delete', $me)
            ->assertTableActionVisible('delete', $other)
            ->callTableAction('enviar_senha', $other)
            ->assertHasNoTableActionErrors();

        Notification::assertSentTo($other, \Filament\Auth\Notifications\ResetPassword::class);
    }
}
