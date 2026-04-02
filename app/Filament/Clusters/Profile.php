<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Profile extends Cluster
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?int $navigationSort = 98;

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return null;
    }

    public static function getNavigationLabel(): string
    {
        return __('settings.profile.title');
    }

    public static function getClusterBreadcrumb(): ?string
    {
        return __('settings.profile.title');
    }
}
