<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Settings extends Cluster
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string | UnitEnum | null $navigationGroup = null;

    protected static ?int $navigationSort = 99;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return __('p2p.nav.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('p2p.nav.settings');
    }

    public static function getClusterBreadcrumb(): ?string
    {
        return __('p2p.nav.settings');
    }
}
