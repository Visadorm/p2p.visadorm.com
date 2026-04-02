<?php

namespace App\Filament\Widgets;

use App\Enums\TradeStatus;
use App\Models\Trade;
use Illuminate\Support\Carbon;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class FeeRevenueChart extends ApexChartWidget
{
    protected static ?int $sort = 8;

    protected static ?string $chartId = 'feeRevenueChart';

    protected static ?string $heading = null;

    protected static bool $deferLoading = true;

    protected int | string | array $columnSpan = 1;

    protected function getHeading(): string
    {
        return __('p2p.widgets.fee_revenue.title');
    }

    protected function getOptions(): array
    {
        $now = Carbon::now();
        $days = collect(range(29, 0))->map(fn ($i) => $now->copy()->subDays($i)->format('Y-m-d'));

        $feeByDay = Trade::where('status', TradeStatus::Completed)
            ->where('updated_at', '>=', $now->copy()->subDays(29)->startOfDay())
            ->selectRaw('DATE(updated_at) as day, SUM(fee_amount) as total_fee')
            ->groupBy('day')
            ->pluck('total_fee', 'day');

        $labels = $days->map(fn ($d) => Carbon::parse($d)->format('M d'))->toArray();
        $data = $days->map(fn ($d) => round((float) ($feeByDay[$d] ?? 0), 4))->toArray();

        return [
            'chart' => [
                'type' => 'line',
                'height' => 200,
                'toolbar' => ['show' => false],
            ],
            'series' => [
                [
                    'name' => __('p2p.widgets.fee_revenue.series_name'),
                    'data' => $data,
                ],
            ],
            'xaxis' => [
                'categories' => $labels,
                'labels' => ['style' => ['colors' => '#9ca3af']],
            ],
            'yaxis' => [
                'labels' => ['style' => ['colors' => '#9ca3af']],
            ],
            'stroke' => ['curve' => 'smooth', 'width' => 2],
            'colors' => ['#f59e0b'],
            'grid' => ['borderColor' => '#374151'],
            'tooltip' => ['theme' => 'dark'],
        ];
    }
}
