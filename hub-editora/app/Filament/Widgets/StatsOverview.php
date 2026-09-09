<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Shipment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '120s';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $paid = fn () => Order::query()->where('payment_status', 'paid');

        $monthStart = now()->startOfMonth();
        $prevMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        $prevMonthSameDay = $prevMonthStart->copy()->addDays(now()->day - 1)->endOfDay();

        $month = (clone $paid())->where('placed_at', '>=', $monthStart);
        $prevMonth = (clone $paid())->whereBetween('placed_at', [$prevMonthStart, $prevMonthSameDay]);

        $monthCount = (clone $month)->count();
        $monthRevenue = (int) (clone $month)->sum('total_cents');
        $prevCount = (clone $prevMonth)->count();
        $prevRevenue = (int) (clone $prevMonth)->sum('total_cents');

        $today = (clone $paid())->whereDate('placed_at', today());
        $week = (clone $paid())->where('placed_at', '>=', now()->subDays(6)->startOfDay());

        // Série diária dos últimos 14 dias para o gráfico miniatura.
        $series = collect(range(13, 0))->map(fn ($d) => (clone $paid())->whereDate('placed_at', today()->subDays($d))->count())->all();
        $revenueSeries = collect(range(13, 0))->map(fn ($d) => (int) (clone $paid())->whereDate('placed_at', today()->subDays($d))->sum('total_cents') / 100)->all();

        $awaiting = Order::query()->awaitingShipment()->count();
        $oldest = Order::query()->awaitingShipment()->min('placed_at');
        $problems = Order::query()->where('status', OrderStatus::Problem)->count();
        $labelsToday = Shipment::query()->whereDate('label_generated_at', today())->count();
        $shippedWeek = Shipment::query()->where('shipped_at', '>=', now()->subDays(6)->startOfDay())->count();

        return [
            Stat::make('Vendas no mês', $this->money($monthRevenue))
                ->description($monthCount.' pedido(s) pagos · '.$this->delta($monthRevenue, $prevRevenue).' vs. mesmo período do mês anterior')
                ->descriptionIcon($monthRevenue >= $prevRevenue ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($monthRevenue >= $prevRevenue ? 'success' : 'danger')
                ->chart($revenueSeries),

            Stat::make('Pedidos pagos', (clone $week)->count().' na semana')
                ->description((clone $today)->count().' hoje · '.$this->money((int) (clone $week)->sum('total_cents')).' em 7 dias')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary')
                ->chart($series),

            Stat::make('A expedir', (string) $awaiting)
                ->description($awaiting
                    ? 'mais antigo aguardando '.Carbon::parse($oldest)->diffForHumans(null, true)
                    : 'fila vazia')
                ->descriptionIcon($awaiting ? 'heroicon-m-clock' : 'heroicon-m-check-circle')
                ->color($awaiting > 5 ? 'warning' : ($awaiting ? 'info' : 'success')),

            Stat::make('Expedição', $shippedWeek.' postados na semana')
                ->description($labelsToday.' etiqueta(s) hoje · '.($problems ? $problems.' pedido(s) com problema' : 'sem pendências'))
                ->descriptionIcon($problems ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-truck')
                ->color($problems ? 'danger' : 'success'),
        ];
    }

    private function money(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }

    private function delta(int $now, int $before): string
    {
        if ($before === 0) {
            return $now > 0 ? '+100%' : '0%';
        }

        $pct = (int) round(($now - $before) / $before * 100);

        return ($pct >= 0 ? '+' : '').$pct.'%';
    }
}
