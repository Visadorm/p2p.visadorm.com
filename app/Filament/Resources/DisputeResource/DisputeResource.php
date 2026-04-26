<?php

namespace App\Filament\Resources\DisputeResource;

use App\Filament\Resources\DisputeResource\Pages\ListDisputes;
use App\Filament\Resources\DisputeResource\Pages\ViewDispute;
use App\Filament\Resources\DisputeResource\Schemas\DisputeInfolist;
use App\Filament\Resources\DisputeResource\Tables\DisputesTable;
use App\Models\Dispute;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DisputeResource extends Resource
{
    protected static ?string $model = Dispute::class;

    protected static ?string $recordTitleAttribute = 'id';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'p2p/disputes';

    public static function getNavigationGroup(): ?string
    {
        return __('p2p.nav.p2p_trading');
    }

    public static function getModelLabel(): string
    {
        return __('trade.dispute');
    }

    public static function getPluralModelLabel(): string
    {
        return __('p2p.disputes');
    }

    public static function infolist(Schema $schema): Schema
    {
        return DisputeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DisputesTable::configure($table);
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
        return parent::getEloquentQuery()->with(['trade.merchant:id,username,wallet_address']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDisputes::route('/'),
            'view' => ViewDispute::route('/{record}'),
        ];
    }
}
