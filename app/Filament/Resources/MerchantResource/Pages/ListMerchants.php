<?php

namespace App\Filament\Resources\MerchantResource\Pages;

use App\Filament\Resources\MerchantResource\MerchantResource;
use App\Models\Merchant;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;

class ListMerchants extends ListRecords
{
    protected static string $resource = MerchantResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(Merchant::count()),
            'active' => Tab::make('Active')
                ->badge(Merchant::where('is_active', true)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('is_active', true)),
            'inactive' => Tab::make('Inactive')
                ->badge(Merchant::where('is_active', false)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('is_active', false)),
            'legendary' => Tab::make('Legendary')
                ->badge(Merchant::where('is_legendary', true)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('is_legendary', true)),
            'online' => Tab::make('Online')
                ->badge(Merchant::where('is_online', true)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('is_online', true)),
        ];
    }
}
