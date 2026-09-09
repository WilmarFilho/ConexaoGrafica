<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset() // "esqueci a senha" por e-mail: ninguem precisa repassar senha
            ->profile()
            ->brandName('Hub Editora')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2.1rem')
            ->favicon(asset('favicon.svg'))
            // Paleta da identidade: azul suave como cor de acao, cinzas quentes
            // no resto (papel). O ciano da Pubcon nao entra: o hub e da editora.
            ->colors([
                'primary' => Color::hex('#2E6BD6'),
                'gray' => Color::Stone,
                'success' => Color::hex('#15803D'),
                'warning' => Color::hex('#B45309'),
                'danger' => Color::hex('#B91C1C'),
                'info' => Color::hex('#2558B5'),
            ])
            ->font('Nunito Sans')
            ->darkMode(false) // identidade e clara e quente; sem tema escuro por enquanto
            ->sidebarWidth('15rem')
            ->maxContentWidth('full')
            // CSS da identidade inline: dispensa `filament:assets` no deploy em cPanel.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<link rel="preconnect" href="https://fonts.googleapis.com">'
                    .'<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">'
                    .'<style>'.file_get_contents(resource_path('css/hub-theme.css')).'</style>',
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
