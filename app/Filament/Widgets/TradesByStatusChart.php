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
            'payment_confirmed' => '#10b981',
            'completed' => '#22c55e',
            'disputed' => '#ef4444',
            'cancelled' => '#6b7280',
            'expired' => '#d1d5db',
            'sell_funded' => '#8b5cf6',
            'in_progress' => '#0ea5e9',
            'awaiting_payment' => '#fbbf24',
            'verified_by_seller' => '#a78bfa',
            'released' => '#34d399',
            'resolved_buyer' => '#06b6d4',
            'resolved_seller' => '#84cc16',
            'resolved' => '#16a34a',
        ];

        $bgColors = [];
        foreach ($statuses as $status) {
            $count = Trade::where('status', $status)->count();
            if ($count > 0) {
                $counts[] = $count;
                $labels[] = $status->getLabel();
                $bgColors[] = $colors[$status->value] ?? '#6b7280';
            }
        }

        return [
            'datasets' => [
                [
                    'data' => $counts,
                    'backgroundColor' => $bgColors,
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
