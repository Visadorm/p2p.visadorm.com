<?php

namespace App\Filament\Resources\ReviewResource\Pages;

use App\Filament\Resources\ReviewResource\ReviewResource;
use App\Models\Review;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;

class ListReviews extends ListRecords
{
    protected static string $resource = ReviewResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(Review::count()),
            'visible' => Tab::make('Visible')
                ->badge(Review::where('is_hidden', false)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('is_hidden', false)),
            'hidden' => Tab::make('Hidden')
                ->badge(Review::where('is_hidden', true)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('is_hidden', true)),
            '5_stars' => Tab::make('5 Stars')
                ->badge(Review::where('rating', 5)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('rating', 5)),
            '1_star' => Tab::make('1 Star')
                ->badge(Review::where('rating', 1)->count())
                ->modifyQueryUsing(fn ($query) => $query->where('rating', 1)),
        ];
    }
}
