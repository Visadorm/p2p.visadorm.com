<?php

namespace App\Filament\Widgets;

use App\Enums\DisputeStatus;
use App\Models\Dispute;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class ActiveDisputesWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $open = Dispute::where('status', DisputeStatus::Open)->count();

        $resolvedThisMonth = Dispute::whereIn('status', [
            DisputeStatus::ResolvedBuyer,
            DisputeStatus::ResolvedMerchant,
        ])
            ->where('updated_at', '>=', Carbon::now()->startOfMonth())
            ->count();

        $total = Dispute::count();

        return [
            Stat::make(__('p2p.widgets.active_disputes.open'), $open)
                ->color('danger'),
            Stat::make(__('p2p.widgets.active_disputes.resolved_this_month'), $resolvedThisMonth)
                ->color('success'),
            Stat::make(__('p2p.widgets.active_disputes.total'), $total)
                ->color('gray'),
        ];
    }
}
