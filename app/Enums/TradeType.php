<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum TradeType: string implements HasColor, HasIcon, HasLabel
{
    case Buy = 'buy';
    case Sell = 'sell';

    public function getLabel(): string
    {
        return match ($this) {
            self::Buy => __('trade.type.buy'),
            self::Sell => __('trade.type.sell'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Buy => 'success',
            self::Sell => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Buy => Heroicon::OutlinedArrowDownTray,
            self::Sell => Heroicon::OutlinedArrowUpTray,
        };
    }
}
