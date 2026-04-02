<?php

namespace App\Filament\Widgets;

use App\Enums\DisputeStatus;
use App\Enums\KycStatus;
use App\Enums\TradeStatus;
use App\Models\Dispute;
use App\Models\KycDocument;
use App\Models\Trade;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TradeStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make(__('p2p.trades_today'), Trade::whereDate('created_at', today())->count())
                ->description(__('p2p.total_volume') . ': ' . number_format((float) Trade::whereDate('created_at', today())->sum('amount_usdc'), 2) . ' USDC')
                ->color('success'),

            Stat::make(__('p2p.trades_this_week'), Trade::where('created_at', '>=', now()->startOfWeek())->count())
                ->description(number_format((float) Trade::where('created_at', '>=', now()->startOfWeek())->sum('amount_usdc'), 2) . ' USDC')
                ->color('primary'),

            Stat::make(__('p2p.trades_this_month'), Trade::where('created_at', '>=', now()->startOfMonth())->count())
                ->description(number_format((float) Trade::where('created_at', '>=', now()->startOfMonth())->sum('amount_usdc'), 2) . ' USDC')
                ->color('info'),

            Stat::make(__('p2p.active_disputes'), Dispute::where('status', DisputeStatus::Open)->count())
                ->color('danger'),

            Stat::make(__('p2p.pending_kyc'), KycDocument::where('status', KycStatus::Pending)->count())
                ->color('warning'),

            Stat::make(__('p2p.completed_trades'), Trade::where('status', TradeStatus::Completed)->count())
                ->color('success'),
        ];
    }
}
