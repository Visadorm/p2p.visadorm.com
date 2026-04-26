<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum TradeStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case EscrowLocked = 'escrow_locked';
    case PaymentSent = 'payment_sent';
    case Completed = 'completed';
    case Disputed = 'disputed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => __('trade.status.pending'),
            self::EscrowLocked => __('trade.status.escrow_locked'),
            self::PaymentSent => __('trade.status.payment_sent'),
            self::Completed => __('trade.status.completed'),
            self::Disputed => __('trade.status.disputed'),
            self::Cancelled => __('trade.status.cancelled'),
            self::Expired => __('trade.status.expired'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::EscrowLocked => 'info',
            self::PaymentSent => 'warning',
            self::Completed => 'success',
            self::Disputed => 'danger',
            self::Cancelled => 'gray',
            self::Expired => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Pending => Heroicon::OutlinedClock,
            self::EscrowLocked => Heroicon::OutlinedLockClosed,
            self::PaymentSent => Heroicon::OutlinedBanknotes,
            self::Completed => Heroicon::OutlinedCheckBadge,
            self::Disputed => Heroicon::OutlinedExclamationTriangle,
            self::Cancelled => Heroicon::OutlinedXCircle,
            self::Expired => Heroicon::OutlinedClock,
        };
    }
}
