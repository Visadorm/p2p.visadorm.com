<?php

namespace App\Filament\Resources\TradeResource\Pages;

use App\Enums\TradeStatus;
use App\Filament\Resources\TradeResource\TradeResource;
use App\Models\Trade;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;

class ListTrades extends ListRecords
{
    protected static string $resource = TradeResource::class;

    public function getTabs(): array
    {
        $inProgressStatuses = [
            TradeStatus::Pending,
            TradeStatus::EscrowLocked,
            TradeStatus::PaymentSent,
            TradeStatus::SellFunded,
            TradeStatus::InProgress,
            TradeStatus::AwaitingPayment,
            TradeStatus::VerifiedBySeller,
            TradeStatus::Released,
        ];

        $completedStatuses = [
            TradeStatus::Completed,
            TradeStatus::Resolved,
            TradeStatus::ResolvedBuyer,
            TradeStatus::ResolvedSeller,
        ];

        return [
            'all' => Tab::make('All')
                ->badge(Trade::count()),
            'buy' => Tab::make('Buy')
                ->badge(Trade::where('type', 'buy')->count())
                ->modifyQueryUsing(fn ($query) => $query->where('type', 'buy')),
            'sell' => Tab::make('Sell')
                ->badge(Trade::where('type', 'sell')->count())
                ->modifyQueryUsing(fn ($query) => $query->where('type', 'sell')),
            'in_progress' => Tab::make('In Progress')
                ->badge(Trade::whereIn('status', $inProgressStatuses)->count())
                ->modifyQueryUsing(fn ($query) => $query->whereIn('status', $inProgressStatuses)),
            'completed' => Tab::make('Completed')
                ->badge(Trade::whereIn('status', $completedStatuses)->count())
                ->modifyQueryUsing(fn ($query) => $query->whereIn('status', $completedStatuses)),
            'disputed' => Tab::make('Disputed')
                ->badge(Trade::where('status', TradeStatus::Disputed)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', TradeStatus::Disputed)),
            'cancelled' => Tab::make('Cancelled')
                ->badge(Trade::whereIn('status', [TradeStatus::Cancelled, TradeStatus::Expired])->count())
                ->modifyQueryUsing(fn ($query) => $query->whereIn('status', [TradeStatus::Cancelled, TradeStatus::Expired])),
        ];
    }
}
