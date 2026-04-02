<?php

namespace App\Filament\Resources\ReviewResource;

use App\Filament\Resources\ReviewResource\Pages\ListReviews;
use App\Filament\Resources\ReviewResource\Tables\ReviewsTable;
use App\Models\Review;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $recordTitleAttribute = 'id';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'p2p/reviews';

    public static function getNavigationGroup(): ?string
    {
        return __('p2p.nav.p2p_trading');
    }

    public static function getModelLabel(): string
    {
        return __('p2p.review');
    }

    public static function getPluralModelLabel(): string
    {
        return __('p2p.reviews');
    }

    public static function table(Table $table): Table
    {
        return ReviewsTable::configure($table);
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
        return parent::getEloquentQuery()->with(['merchant:id,username']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviews::route('/'),
        ];
    }
}
