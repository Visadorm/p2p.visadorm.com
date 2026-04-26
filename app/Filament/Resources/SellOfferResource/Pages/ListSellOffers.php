<?php

declare(strict_types=1);

namespace App\Filament\Resources\SellOfferResource\Pages;

use App\Filament\Resources\SellOfferResource\SellOfferResource;
use Filament\Resources\Pages\ListRecords;

class ListSellOffers extends ListRecords
{
    protected static string $resource = SellOfferResource::class;
}
