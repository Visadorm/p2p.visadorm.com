<?php

namespace App\Filament\Resources\MerchantResource;

use App\Filament\Resources\MerchantResource\Pages\EditMerchant;
use App\Filament\Resources\MerchantResource\Pages\ListMerchants;
use App\Filament\Resources\MerchantResource\Pages\ViewMerchant;
use App\Filament\Resources\MerchantResource\Schemas\MerchantForm;
use App\Filament\Resources\MerchantResource\Schemas\MerchantInfolist;
use App\Filament\Resources\MerchantResource\Tables\MerchantsTable;
use App\Models\Merchant;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MerchantResource extends Resource
{
    protected static ?string $model = Merchant::class;

    protected static ?string $recordTitleAttribute = 'username';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'p2p/merchants';

    public static function getNavigationGroup(): ?string
    {
        return __('p2p.nav.p2p_trading');
    }

    public static function getModelLabel(): string
    {
        return __('merchant.merchant');
    }

    public static function getPluralModelLabel(): string
    {
        return __('merchant.merchants');
    }

    public static function form(Schema $schema): Schema
    {
        return MerchantForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MerchantInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MerchantsTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['rank:id,name']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMerchants::route('/'),
            'edit' => EditMerchant::route('/{record}/edit'),
            'view' => ViewMerchant::route('/{record}'),
        ];
    }
}
