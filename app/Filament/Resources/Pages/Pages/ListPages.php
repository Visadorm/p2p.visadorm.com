<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Pages;

use App\Enums\PageStatus;
use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('page.admin.tab_all'))
                ->badge(fn () => Page::query()->count()),
            'published' => Tab::make(__('page.admin.tab_published'))
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PageStatus::Published))
                ->badge(fn () => Page::query()->where('status', PageStatus::Published)->count())
                ->badgeColor('success'),
            'draft' => Tab::make(__('page.admin.tab_draft'))
                ->icon('heroicon-o-pencil-square')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PageStatus::Draft))
                ->badge(fn () => Page::query()->where('status', PageStatus::Draft)->count())
                ->badgeColor('gray'),
        ];
    }
}
