<?php

namespace App\Filament\Resources\MerchantRankResource\Pages;

use App\Filament\Resources\MerchantRankResource\MerchantRankResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMerchantRank extends EditRecord
{
    protected static string $resource = MerchantRankResource::class;

    protected function getActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
