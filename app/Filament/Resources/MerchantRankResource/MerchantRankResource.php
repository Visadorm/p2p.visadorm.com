<?php

namespace App\Filament\Resources\MerchantRankResource;

use App\Filament\Resources\MerchantRankResource\Pages\EditMerchantRank;
use App\Filament\Resources\MerchantRankResource\Pages\ListMerchantRanks;
use App\Filament\Resources\MerchantRankResource\Schemas\MerchantRankForm;
use App\Filament\Resources\MerchantRankResource\Tables\MerchantRanksTable;
use App\Models\MerchantRank;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MerchantRankResource extends Resource
{
    protected static ?string $model = MerchantRank::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'verification/merchant-ranks';

    public static function getNavigationGroup(): ?string
    {
        return __('p2p.nav.verification');
    }

    public static function getModelLabel(): string
    {
        return __('p2p.merchant_rank');
    }

    public static function getPluralModelLabel(): string
    {
        return __('p2p.merchant_ranks');
    }

    public static function form(Schema $schema): Schema
    {
        return MerchantRankForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MerchantRanksTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMerchantRanks::route('/'),
            'edit' => EditMerchantRank::route('/{record}/edit'),
        ];
    }
}
