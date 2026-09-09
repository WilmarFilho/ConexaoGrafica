<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ChannelsHealth;
use App\Filament\Widgets\LatestOrders;
use App\Filament\Widgets\OrdersChart;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TopProducts;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Visão geral';

    protected static ?string $navigationLabel = 'Visão geral';

    public function getSubheading(): ?string
    {
        return 'Editora inteira: loja, landing pages e expedição num só lugar.';
    }

    public function getWidgets(): array
    {
        return [
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
