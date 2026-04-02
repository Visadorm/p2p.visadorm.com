<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum TradingLinkType: string implements HasColor, HasIcon, HasLabel
{
    case Public = 'public';
    case Private = 'private';

    public function getLabel(): string
    {
        return match ($this) {
            self::Public => __('merchant.link_type.public'),
            self::Private => __('merchant.link_type.private'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Public => 'success',
            self::Private => 'warning',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Public => Heroicon::OutlinedGlobeAlt,
            self::Private => Heroicon::OutlinedLockClosed,
        };
    }
}
