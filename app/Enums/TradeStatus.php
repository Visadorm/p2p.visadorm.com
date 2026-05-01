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
    case Confirming = 'confirming';
    case Completed = 'completed';
    case Cancelling = 'cancelling';
    case Disputed = 'disputed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Resolved = 'resolved';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => __('trade.status.pending'),
            self::EscrowLocked => __('trade.status.escrow_locked'),
            self::PaymentSent => __('trade.status.payment_sent'),
            self::Confirming => __('trade.status.confirming'),
            self::Completed => __('trade.status.completed'),
            self::Cancelling => __('trade.status.cancelling'),
            self::Disputed => __('trade.status.disputed'),
            self::Cancelled => __('trade.status.cancelled'),
            self::Expired => __('trade.status.expired'),
            self::Resolved => __('trade.status.resolved'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::EscrowLocked => 'info',
            self::PaymentSent => 'warning',
            self::Confirming => 'warning',
            self::Completed => 'success',
            self::Cancelling => 'gray',
            self::Disputed => 'danger',
            self::Cancelled => 'gray',
            self::Expired => 'gray',
            self::Resolved => 'success',
        };
    }

    /**
     * Statuses that count as an "active" sell trade for the seller.
     * Used to prevent a seller from opening multiple concurrent sell trades.
     */
    public static function activeSellStatuses(): array
    {
        return [
            self::Pending,
            self::EscrowLocked,
            self::PaymentSent,
            self::Confirming,
            self::Cancelling,
            self::Disputed,
        ];
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Pending => Heroicon::OutlinedClock,
            self::EscrowLocked => Heroicon::OutlinedLockClosed,
            self::PaymentSent => Heroicon::OutlinedBanknotes,
            self::Confirming => Heroicon::OutlinedArrowPath,
            self::Completed => Heroicon::OutlinedCheckBadge,
            self::Cancelling => Heroicon::OutlinedArrowPath,
            self::Disputed => Heroicon::OutlinedExclamationTriangle,
            self::Cancelled => Heroicon::OutlinedXCircle,
            self::Expired => Heroicon::OutlinedClock,
            self::Resolved => Heroicon::OutlinedCheckBadge,
        };
    }
}
