<?php

namespace App\Filament\Resources\DisputeResource\Pages;

use App\Enums\DisputeStatus;
use App\Filament\Resources\DisputeResource\DisputeResource;
use App\Models\Dispute;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;

class ListDisputes extends ListRecords
{
    protected static string $resource = DisputeResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(Dispute::count()),
            'open' => Tab::make('Open')
                ->badge(Dispute::where('status', DisputeStatus::Open)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', DisputeStatus::Open)),
            'resolved_buyer' => Tab::make('Resolved (Buyer)')
                ->badge(Dispute::where('status', DisputeStatus::ResolvedBuyer)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', DisputeStatus::ResolvedBuyer)),
            'resolved_merchant' => Tab::make('Resolved (Merchant)')
                ->badge(Dispute::where('status', DisputeStatus::ResolvedMerchant)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', DisputeStatus::ResolvedMerchant)),
        ];
    }
}
