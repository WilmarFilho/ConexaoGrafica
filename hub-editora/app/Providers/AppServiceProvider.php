<?php

namespace App\Providers;

use App\Editorial\Import\Ai\AiGateway;
use App\Editorial\Import\Ai\OpenAiGateway;
use App\Models\Book;
use App\Models\Chapter;
use App\Support\HubSettings;
use App\Support\SecurityAudit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Importação de PDF com IA: o Analyzer fala com a API da OpenAI só por esta
        // interface (os testes trocam por um fake; sem chave nenhuma chamada sai).
        $this->app->bind(AiGateway::class, OpenAiGateway::class);
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

        // Contribuições polimórficas do módulo Editorial guardam `book` / `chapter`
        // (contrato, seção 2), não o nome da classe. Mapa não forçado: os
        // demais morphs (sync_logs.subject) continuam com a classe.
        Relation::morphMap([
            'book' => Book::class,
            'chapter' => Chapter::class,
        ]);

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
