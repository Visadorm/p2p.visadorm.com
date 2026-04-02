<?php

namespace App\Filament\Widgets;

use App\Models\Trade;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class TradeVolumeChart extends ChartWidget
{
    protected ?string $heading = null;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '200px';

    public function getHeading(): ?string
    {
        return __('p2p.trade_volume_chart');
    }

    protected function getData(): array
    {
        $data = collect(range(29, 0))->map(function ($daysAgo) {
            $date = Carbon::today()->subDays($daysAgo);

            return [
                'date' => $date->format('M d'),
                'volume' => (float) Trade::whereDate('created_at', $date)->sum('amount_usdc'),
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => __('p2p.total_volume') . ' (USDC)',
                    'data' => $data->pluck('volume')->toArray(),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $data->pluck('date')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
