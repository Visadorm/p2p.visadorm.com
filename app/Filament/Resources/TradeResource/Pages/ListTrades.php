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
        return [
            'all' => Tab::make('All')
                ->badge(Trade::count()),
            'pending' => Tab::make('Pending')
                ->badge(Trade::where('status', TradeStatus::Pending)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', TradeStatus::Pending)),
            'escrow_locked' => Tab::make('Escrow Locked')
                ->badge(Trade::where('status', TradeStatus::EscrowLocked)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', TradeStatus::EscrowLocked)),
            'payment_sent' => Tab::make('Payment Sent')
                ->badge(Trade::where('status', TradeStatus::PaymentSent)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', TradeStatus::PaymentSent)),
            'completed' => Tab::make('Completed')
                ->badge(Trade::where('status', TradeStatus::Completed)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', TradeStatus::Completed)),
            'disputed' => Tab::make('Disputed')
                ->badge(Trade::where('status', TradeStatus::Disputed)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', TradeStatus::Disputed)),
            'cancelled' => Tab::make('Cancelled')
                ->badge(Trade::where('status', TradeStatus::Cancelled)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', TradeStatus::Cancelled)),
            'expired' => Tab::make('Expired')
                ->badge(Trade::where('status', TradeStatus::Expired)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', TradeStatus::Expired)),
        ];
    }
}
