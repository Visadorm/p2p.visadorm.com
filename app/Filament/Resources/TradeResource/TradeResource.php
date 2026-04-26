<?php

namespace App\Filament\Resources\TradeResource;

use App\Filament\Resources\TradeResource\Pages\ListTrades;
use App\Filament\Resources\TradeResource\Pages\ViewTrade;
use App\Filament\Resources\TradeResource\Schemas\TradeInfolist;
use App\Filament\Resources\TradeResource\Tables\TradesTable;
use App\Models\Trade;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TradeResource extends Resource
{
    protected static ?string $model = Trade::class;

    protected static ?string $recordTitleAttribute = 'trade_hash';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'p2p/trades';

    public static function getNavigationGroup(): ?string
    {
        return __('p2p.nav.p2p_trading');
    }

    public static function getModelLabel(): string
    {
        return __('trade.trade');
    }

    public static function getPluralModelLabel(): string
    {
        return __('trade.trades');
    }

    public static function infolist(Schema $schema): Schema
    {
        return TradeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TradesTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()?->role, ['super_admin', 'dispute_manager']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['merchant:id,username,wallet_address']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrades::route('/'),
            'view' => ViewTrade::route('/{record}'),
        ];
    }
}
