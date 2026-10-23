<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AlertsBanner;
use App\Filament\Widgets\ChannelsHealth;
use App\Filament\Widgets\LatestOrders;
use App\Filament\Widgets\OrdersChart;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TopProducts;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Visão geral';

    protected static ?string $navigationLabel = 'Visão geral';

    /** Conta de vendedor cai direto no portal do vendedor. */
    public function mount(): void
    {
        if (auth()->user()?->isVendor()) {
            // redirect() do Livewire no mount quebra no carregamento da página inteira
            throw new HttpResponseException(new RedirectResponse(VendorPortal::getUrl()));
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->isStaff();
    }

    public function getSubheading(): ?string
    {
        return 'Editora inteira: loja, landing pages e expedição num só lugar.';
    }

    public function getWidgets(): array
    {
        if (auth()->user()?->isVendor()) {
            return []; // vai para o portal; nada da editora é montado
        }

        return [
            AlertsBanner::class,
            StatsOverview::class,
            OrdersChart::class,
            ChannelsHealth::class,
            LatestOrders::class,
            TopProducts::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 12;
    }
}
