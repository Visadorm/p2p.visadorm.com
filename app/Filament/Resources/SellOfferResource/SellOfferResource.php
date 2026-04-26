<?php

declare(strict_types=1);

namespace App\Filament\Resources\SellOfferResource;

use App\Filament\Resources\SellOfferResource\Pages\ListSellOffers;
use App\Filament\Resources\SellOfferResource\Pages\ViewSellOffer;
use App\Filament\Resources\SellOfferResource\Schemas\SellOfferInfolist;
use App\Filament\Resources\SellOfferResource\Tables\SellOffersTable;
use App\Models\SellOffer;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SellOfferResource extends Resource
{
    protected static ?string $model = SellOffer::class;

    protected static ?string $recordTitleAttribute = 'slug';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'p2p/sell-offers';

    public static function getNavigationGroup(): ?string
    {
        return __('p2p.nav.p2p_trading');
    }

    public static function getModelLabel(): string
    {
        return __('p2p.sell_offer');
    }

    public static function getPluralModelLabel(): string
    {
        return __('p2p.sell_offers');
    }

    public static function table(Table $table): Table
    {
        return SellOffersTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SellOfferInfolist::configure($schema);
    }

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()?->role, ['super_admin', 'dispute_manager']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSellOffers::route('/'),
            'view' => ViewSellOffer::route('/{record}'),
        ];
    }
}
