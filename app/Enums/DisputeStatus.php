<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum DisputeStatus: string implements HasColor, HasIcon, HasLabel
{
    case Open = 'open';
    case ResolvedBuyer = 'resolved_buyer';
    case ResolvedMerchant = 'resolved_merchant';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => __('trade.dispute_status.open'),
            self::ResolvedBuyer => __('trade.dispute_status.resolved_buyer'),
            self::ResolvedMerchant => __('trade.dispute_status.resolved_merchant'),
            self::Cancelled => __('trade.dispute_status.cancelled'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::ResolvedBuyer => 'success',
            self::ResolvedMerchant => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Open => Heroicon::OutlinedExclamationTriangle,
            self::ResolvedBuyer => Heroicon::OutlinedCheckCircle,
            self::ResolvedMerchant => Heroicon::OutlinedCheckCircle,
            self::Cancelled => Heroicon::OutlinedXCircle,
        };
    }
}
