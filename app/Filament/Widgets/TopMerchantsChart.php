<?php

namespace App\Filament\Widgets;

use App\Models\Merchant;
use Filament\Widgets\ChartWidget;

class TopMerchantsChart extends ChartWidget
{
    protected ?string $heading = null;

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '200px';

    public function getHeading(): ?string
    {
        return __('p2p.top_merchants');
    }

    protected function getData(): array
    {
        $merchants = Merchant::orderByDesc('total_volume')
            ->limit(10)
            ->get(['username', 'total_volume']);

        return [
            'datasets' => [
                [
                    'label' => __('p2p.total_volume') . ' (USDC)',
                    'data' => $merchants->pluck('total_volume')->map(fn ($v) => (float) $v)->toArray(),
                    'backgroundColor' => '#f59e0b',
                ],
            ],
            'labels' => $merchants->pluck('username')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
