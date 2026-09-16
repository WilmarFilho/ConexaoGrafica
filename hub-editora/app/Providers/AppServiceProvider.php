<?php

namespace App\Providers;

use App\Support\HubSettings;
use App\Support\SecurityAudit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Em produção o hub só existe atrás do AutoSSL do cPanel: links e
        // assets sempre em https, mesmo se alguém entrar por http.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Chaves gravadas pela tela Integrações vencem o .env.
        if (! $this->app->runningUnitTests()) {
            HubSettings::applyToConfig();
        }

        // Senhas: 12+ caracteres, maiúsculas e minúsculas, número, símbolo e
        // não constar em vazamentos conhecidos (política exigida pela Amazon).
        Password::defaults(fn () => Password::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised());

        // Trilha de autenticação: quem entrou, quem errou, quem saiu.
        Event::listen(Login::class, fn (Login $e) => SecurityAudit::login($e->user, request()));
        Event::listen(Failed::class, fn (Failed $e) => SecurityAudit::failed($e->credentials['email'] ?? null, request()));
        Event::listen(Logout::class, fn (Logout $e) => SecurityAudit::logout($e->user, request()));
    }
}
