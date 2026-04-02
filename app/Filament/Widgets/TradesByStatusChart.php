<?php

namespace App\Filament\Widgets;

use App\Enums\TradeStatus;
use App\Models\Trade;
use Filament\Widgets\ChartWidget;

class TradesByStatusChart extends ChartWidget
{
    protected ?string $heading = null;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '200px';

    public function getHeading(): ?string
    {
        return __('p2p.trades_by_status');
    }

    protected function getData(): array
    {
        $statuses = TradeStatus::cases();
        $counts = [];
        $labels = [];
        $colors = [
            'pending' => '#9ca3af',
            'escrow_locked' => '#3b82f6',
            'payment_sent' => '#f59e0b',
            'completed' => '#22c55e',
            'disputed' => '#ef4444',
            'cancelled' => '#6b7280',
            'expired' => '#d1d5db',
        ];

        foreach ($statuses as $status) {
            $count = Trade::where('status', $status)->count();
            if ($count > 0) {
                $counts[] = $count;
                $labels[] = $status->getLabel();
            }
        }

        return [
            'datasets' => [
                [
                    'data' => $counts,
                    'backgroundColor' => array_slice(array_values($colors), 0, count($counts)),
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
