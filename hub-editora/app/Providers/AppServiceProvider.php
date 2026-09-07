<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
    }
}
