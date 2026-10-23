<?php

namespace App\Filament\Widgets;

use App\Models\Channel;
use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos pagos por dia nos últimos 30 dias, uma linha por canal.
 * Cores fixas por canal, as mesmas dos selos nas tabelas.
 */
class OrdersChart extends ChartWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isStaff();
    }

    protected int|string|array $columnSpan = 8;

    protected ?string $heading = 'Pedidos pagos por dia';

    protected ?string $description = 'Últimos 30 dias, por canal';

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = null;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(fn ($d) => today()->subDays($d));
        $labels = $days->map(fn ($d) => $d->format('d/m'))->all();

        $rows = Order::query()
            ->where('payment_status', 'paid')
            ->where('placed_at', '>=', $days->first()->startOfDay())
            ->select(['channel_id', DB::raw('DATE(placed_at) as dia'), DB::raw('count(*) as total')])
            ->groupBy('channel_id', 'dia')
            ->get()
            ->groupBy('channel_id');

        $palette = [
            // cores de canal do redesign (mesmas dos selos)
            Channel::WOOCOMMERCE => '#5B9BFF',
            Channel::PAGARME => '#5BD98A',
            Channel::AMAZON => '#F5B544',
        ];

        $datasets = [];
        foreach (Channel::query()->whereIn('slug', array_keys($palette))->get() as $channel) {
            $byDay = ($rows[$channel->id] ?? collect())->keyBy('dia');
            $datasets[] = [
                'label' => $channel->name,
                'data' => $days->map(fn ($d) => (int) ($byDay[$d->toDateString()]->total ?? 0))->all(),
                'borderColor' => $palette[$channel->slug],
                'backgroundColor' => $palette[$channel->slug].'22',
                'fill' => true,
                'tension' => 0.35,
                'pointRadius' => 0,
                'borderWidth' => 2,
            ];
        }

        return ['datasets' => $datasets, 'labels' => $labels];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom', 'labels' => ['color' => '#8D8D8D', 'usePointStyle' => true, 'boxWidth' => 8]]],
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0, 'color' => '#8D8D8D', 'font' => ['family' => 'DM Mono']], 'grid' => ['color' => 'rgba(141, 141, 141, 0.18)'], 'border' => ['display' => false]],
                'x' => ['grid' => ['display' => false], 'ticks' => ['color' => '#8D8D8D', 'font' => ['family' => 'DM Mono']]],
            ],
            'interaction' => ['mode' => 'index', 'intersect' => false],
        ];
    }
}
