<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EnsurePasswordIsFresh;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
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
    /** Azul elétrico: 400 é o acento de interface, 950 a superfície de acento. */
    public const PRIMARY = [
        50 => '#EAF2FF', 100 => '#D6E6FF', 200 => '#ADCBFF', 300 => '#85B0FF', 400 => '#5B9BFF',
        500 => '#3B82F6', 600 => '#2563EB', 700 => '#1D4ED8', 800 => '#1E3A8A', 900 => '#172554', 950 => '#0F1E38',
    ];

    /** Neutro sem tintura: 950 fundo, 900 cartão, 800 elevado, 700 borda, 400 texto secundário. */
    public const GRAY = [
        50 => '#F2F2F2', 100 => '#E0E0E0', 200 => '#C9C9C9', 300 => '#A8A8A8', 400 => '#8D8D8D',
        500 => '#6A6A6A', 600 => '#4A4A4A', 700 => '#2A2A2A', 800 => '#1C1C1C', 900 => '#111111', 950 => '#0A0A0A',
    ];

    public static function topbarDate(): string
    {
        $now = now()->locale('pt_BR');

        return ucfirst(str_replace('-feira', '', $now->dayName)).', '.$now->format('d').' '.$now->shortMonthName.' · '.$now->format('H:i');
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Pages\Auth\Login::class)
            // "esqueci a senha" por e-mail: ninguem precisa repassar senha. Perfil e
            // redefinicao aplicam a politica completa (nome/e-mail, historico, idade minima).
            ->passwordReset(resetAction: \App\Filament\Pages\Auth\ResetPassword::class)
            ->profile(\App\Filament\Pages\Auth\EditProfile::class)
            // Dois fatores por aplicativo autenticador, obrigatorio para todos
            // (exigencia da Amazon para dados de compradores).
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ], isRequired: true)
            ->brandName('Hub Editora')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2.1rem')
            ->favicon(asset('favicon.svg'))
            // Identidade do redesign (out/2026): escuro editorial com acento azul
            // elétrico. Escalas primary/gray são os tokens do Claude Design;
            // superfícies e componentes ficam em resources/css/hub-theme.css.
            ->colors([
                'primary' => self::PRIMARY,
                'gray' => self::GRAY,
                'success' => Color::hex('#5BD98A'),
                'warning' => Color::hex('#F5B544'),
                'danger' => Color::hex('#FF8A8A'),
                'info' => Color::hex('#7CB4FF'),
            ])
            ->font('Inter')
            // Só o tema escuro foi desenhado: fica fixo (sem alternância no menu).
            ->darkMode(true, isForced: true)
            ->defaultThemeMode(ThemeMode::Dark)
            ->sidebarWidth('15rem')
            ->maxContentWidth('full')
            ->navigationGroups([
                NavigationGroup::make('Publicações'),
                NavigationGroup::make('Vendedores'),
                NavigationGroup::make('Administração'),
            ])
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldKeyBindingSuffix()
            // CSS da identidade inline: dispensa `filament:assets` no deploy em cPanel.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<link rel="preconnect" href="https://fonts.googleapis.com">'
                    .'<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
                    .'<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@1,9..144,300;1,9..144,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">'
                    .'<style>'.file_get_contents(resource_path('css/hub-theme.css')).'</style>',
            )
            // Login em duas metades: texto editorial à esquerda, formulário à direita.
            ->renderHook(
                PanelsRenderHook::SIMPLE_LAYOUT_START,
                fn (): string => view('filament.auth.login-hero')->render(),
                scopes: \App\Filament\Pages\Auth\Login::class,
            )
            ->renderHook(
                PanelsRenderHook::SIMPLE_PAGE_START,
                fn (): string => '<div class="hub-eyebrow hub-login-eyebrow">Login</div>',
                scopes: \App\Filament\Pages\Auth\Login::class,
            )
            // Data por extenso ao lado do menu do usuário ("Sexta, 23 out · 15:10").
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => '<span class="hub-topbar-date">'.e(self::topbarDate()).'</span>',
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
                EnsurePasswordIsFresh::class,
            ]);
    }
}
