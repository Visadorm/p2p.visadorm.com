<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum StakePaidBy: string implements HasColor, HasIcon, HasLabel
{
    case Buyer = 'buyer';
    case Merchant = 'merchant';

    public function getLabel(): string
    {
        return match ($this) {
            self::Buyer => __('trade.stake_paid_by.buyer'),
            self::Merchant => __('trade.stake_paid_by.merchant'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Buyer => 'info',
            self::Merchant => 'primary',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Buyer => Heroicon::OutlinedUser,
            self::Merchant => Heroicon::OutlinedBuildingStorefront,
        };
    }
}
