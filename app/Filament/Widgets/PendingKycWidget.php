<?php

namespace App\Filament\Widgets;

use App\Enums\KycStatus;
use App\Models\KycDocument;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class PendingKycWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $pending = KycDocument::where('status', KycStatus::Pending)->count();

        $approvedThisMonth = KycDocument::where('status', KycStatus::Approved)
            ->where('updated_at', '>=', Carbon::now()->startOfMonth())
            ->count();

        $rejected = KycDocument::where('status', KycStatus::Rejected)->count();

        return [
            Stat::make(__('p2p.widgets.pending_kyc.pending'), $pending)
                ->color('warning'),
            Stat::make(__('p2p.widgets.pending_kyc.approved_this_month'), $approvedThisMonth)
                ->color('success'),
            Stat::make(__('p2p.widgets.pending_kyc.rejected'), $rejected)
                ->color('danger'),
        ];
    }
}
