<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\PasswordHistory;
use App\Models\SyncLog;
use App\Models\User;
use App\Notifications\AccessReview;
use App\Notifications\SecurityAlert;
use App\Rules\SenhaDaPolitica;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function passes(?User $user, string $password, bool $minAge = false): bool
    {
        return Validator::make(['password' => $password], ['password' => [new SenhaDaPolitica($user, $minAge)]])->passes();
    }

    public function test_password_cannot_contain_name_or_email_parts(): void
    {
        $u = User::factory()->create(['name' => 'Wilmar José Ferreira', 'email' => 'expedicao.goiania@editora.com', 'password_changed_at' => now()->subDays(3)]);

        $this->assertFalse($this->passes($u, 'Wilmar-2026-Forte!'));
        $this->assertFalse($this->passes($u, 'Senha#Ferreira99x'));
        $this->assertFalse($this->passes($u, 'Goiania!Hub-2026'));
        $this->assertFalse($this->passes($u, 'joSe!Hub-20261234'), 'sem acento e sem diferenciar maiúsculas');
        $this->assertTrue($this->passes($u, 'Livro-Azul-2026!x'));
    }

    public function test_last_ten_passwords_cannot_be_reused_and_history_is_capped(): void
    {
        $u = User::factory()->create(['password' => 'Primeira-Senha-01!', 'password_changed_at' => now()->subDays(3)]);

        foreach (range(2, 13) as $n) {
            $u->password = sprintf('Senha-Numero-%02d!x', $n);
            $u->save();
        }

        $u->refresh();
        $this->assertSame(User::PASSWORD_HISTORY, PasswordHistory::where('user_id', $u->id)->count());
        $this->assertFalse($this->passes($u, 'Senha-Numero-13!x'), 'senha atual');
        $this->assertFalse($this->passes($u, 'Senha-Numero-05!x'), 'entre as 10 anteriores');
        $this->assertTrue($this->passes($u, 'Primeira-Senha-01!'), 'mais antiga que as 10 últimas');
    }

    public function test_voluntary_change_needs_one_day_but_the_reset_link_does_not(): void
    {
        $u = User::factory()->create(['password_changed_at' => now()->subHours(2)]);

        $this->assertFalse($this->passes($u, 'Livro-Azul-2026!x', minAge: true));
        $this->assertTrue($this->passes($u, 'Livro-Azul-2026!x', minAge: false));

        $u->forceFill(['password_changed_at' => now()->subDays(2)])->save();
        $this->assertTrue($this->passes($u->fresh(), 'Livro-Azul-2026!x', minAge: true));
    }

    public function test_profile_page_applies_the_full_policy(): void
    {
        $u = User::factory()->create(['name' => 'Ana Expedição', 'password_changed_at' => now()->subDays(3), 'app_authentication_secret' => 'segredo']);
        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditProfile::class)
            ->fillForm(['password' => 'Ana!Senha-Forte-2026', 'passwordConfirmation' => 'Ana!Senha-Forte-2026'])
            ->call('save')
            ->assertHasFormErrors(['password']);
    }

    public function test_ten_failures_lock_the_account_and_a_login_clears_the_count(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        $alvo = User::factory()->expedicao()->create(['email' => 'alvo@editora.com']);
        $panel = Filament::getPanel('admin');

        for ($i = 0; $i < User::LOCK_AFTER_FAILURES; $i++) {
            event(new Failed('web', null, ['email' => 'alvo@editora.com', 'password' => 'errada']));
        }

        $alvo->refresh();
        $this->assertTrue($alvo->isLocked());
        $this->assertFalse($alvo->canAccessPanel($panel));
        $this->assertSame(1, SyncLog::where('action', 'auth.locked')->count());
        Notification::assertSentTo($admin, SecurityAlert::class, fn ($n) => str_contains($n->message, 'bloqueada'));
        Notification::assertNotSentTo($alvo, SecurityAlert::class, fn ($n) => str_contains($n->message, 'bloqueada'));

        // Mais tentativas durante o bloqueio não estendem nem geram novo bloqueio.
        event(new Failed('web', null, ['email' => 'alvo@editora.com', 'password' => 'errada']));
        $this->assertSame(1, SyncLog::where('action', 'auth.locked')->count());

        // Depois do prazo, um login certo zera tudo.
        $alvo->forceFill(['locked_until' => now()->subMinute()])->save();
        event(new Login('web', $alvo->fresh(), false));
        $alvo->refresh();
        $this->assertSame(0, $alvo->failed_logins);
        $this->assertNull($alvo->locked_until);
        $this->assertNotNull($alvo->last_login_at);
        $this->assertTrue($alvo->canAccessPanel($panel));
    }

    public function test_disabled_users_lose_access_and_admins_can_unlock(): void
    {
        $admin = User::factory()->create();
        $bloqueado = User::factory()->expedicao()->create(['locked_until' => now()->addMinutes(20), 'failed_logins' => 10]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListUsers::class)
            ->assertTableActionVisible('desbloquear', $bloqueado)
            ->callTableAction('desbloquear', $bloqueado)
            ->callTableAction('desativar', $bloqueado);

        $bloqueado->refresh();
        $this->assertFalse($bloqueado->isLocked());
        $this->assertNotNull($bloqueado->disabled_at);
        $this->assertFalse($bloqueado->canAccessPanel(Filament::getPanel('admin')));
        $this->assertSame(1, SyncLog::where('action', 'user.disabled')->count());
    }

    public function test_expedition_profile_cannot_open_users_integrations_or_audit(): void
    {
        $this->seed(\Database\Seeders\ChannelSeeder::class);
        $exp = User::factory()->expedicao()->create(['app_authentication_secret' => 'segredo']);
        $admin = User::factory()->create(['app_authentication_secret' => 'segredo']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $urls = [
            \App\Filament\Resources\Users\UserResource::getUrl(),
            \App\Filament\Pages\Integracoes::getUrl(),
            \App\Filament\Resources\SyncLogs\SyncLogResource::getUrl(),
        ];

        foreach ($urls as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        foreach ($urls as $url) {
            $this->assertContains($this->actingAs($exp)->get($url)->status(), [403, 404], "{$url} aberto para a expedição");
        }

        $this->actingAs($exp)->get('/admin/orders')->assertOk();
    }

    public function test_quarterly_access_review_disables_dormant_expedition_accounts(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['last_login_at' => now()->subDays(200), 'created_at' => now()->subYear()]);
        $dormente = User::factory()->expedicao()->create(['last_login_at' => now()->subDays(120), 'created_at' => now()->subYear()]);
        $ativo = User::factory()->expedicao()->create(['last_login_at' => now()->subDays(10), 'created_at' => now()->subYear()]);
        $novo = User::factory()->expedicao()->create(['created_at' => now()->subDays(5)]);

        $this->artisan('hub:access-review')->assertSuccessful();

        $this->assertNotNull($dormente->fresh()->disabled_at);
        $this->assertNull($ativo->fresh()->disabled_at);
        $this->assertNull($novo->fresh()->disabled_at, 'conta nova ainda não teve tempo de usar');
        $this->assertNull($admin->fresh()->disabled_at, 'administrador nunca é desativado automaticamente');
        Notification::assertSentTo($admin, AccessReview::class, fn ($n) => count($n->users) === 4 && $n->disabled === [$dormente->email]);
        Notification::assertNotSentTo($ativo, AccessReview::class);
        $this->assertSame(1, SyncLog::where('action', 'access.review.sent')->count());

        // O administrador registra a revisão pela tela.
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListUsers::class)->callAction('revisao', data: ['notas' => 'Conferido com a equipe']);
        $this->assertStringContainsString('Conferido', SyncLog::where('action', 'access.review.confirmed')->first()->message);
    }

    public function test_monthly_security_scan_records_and_alerts_findings(): void
    {
        Notification::fake();
        User::factory()->create();

        Process::fake(['*' => Process::result(json_encode(['advisories' => [
            'guzzlehttp/psr7' => [['advisoryId' => 'PKSA-1', 'cve' => 'CVE-2026-0001', 'title' => 'Header injection', 'severity' => 'high']],
        ], 'abandoned' => []]))]);
        $this->artisan('hub:security-scan')->assertSuccessful();

        $log = SyncLog::where('action', 'security.scan')->latest('id')->first();
        $this->assertSame('warning', $log->level);
        $this->assertStringContainsString('CVE-2026-0001', $log->message);
        Notification::assertSentTimes(SecurityAlert::class, 1);

        Process::fake(['*' => Process::result(json_encode(['advisories' => [], 'abandoned' => []]))]);
        $this->artisan('hub:security-scan')->assertSuccessful();
        $this->assertSame('info', SyncLog::where('action', 'security.scan')->latest('id')->first()->level);
    }
}
