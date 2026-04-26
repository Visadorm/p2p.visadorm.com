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

    case SellFunded = 'sell_funded';
    case InProgress = 'in_progress';
    case AwaitingPayment = 'awaiting_payment';
    case VerifiedBySeller = 'verified_by_seller';
    case Released = 'released';
    case ResolvedBuyer = 'resolved_buyer';
    case ResolvedSeller = 'resolved_seller';
    case Resolved = 'resolved';

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
            self::SellFunded => __('trade.status.sell_funded'),
            self::InProgress => __('trade.status.in_progress'),
            self::AwaitingPayment => __('trade.status.awaiting_payment'),
            self::VerifiedBySeller => __('trade.status.verified_by_seller'),
            self::Released => __('trade.status.released'),
            self::ResolvedBuyer => __('trade.status.resolved_buyer'),
            self::ResolvedSeller => __('trade.status.resolved_seller'),
            self::Resolved => __('trade.status.resolved'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::EscrowLocked, self::InProgress => 'info',
            self::PaymentSent, self::AwaitingPayment, self::VerifiedBySeller, self::Released, self::SellFunded => 'warning',
            self::Completed, self::ResolvedSeller => 'success',
            self::Disputed => 'danger',
            self::ResolvedBuyer => 'info',
            self::Resolved => 'success',
            self::Cancelled, self::Expired => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Pending, self::Expired => Heroicon::OutlinedClock,
            self::EscrowLocked, self::SellFunded => Heroicon::OutlinedLockClosed,
            self::PaymentSent, self::AwaitingPayment, self::InProgress => Heroicon::OutlinedBanknotes,
            self::VerifiedBySeller, self::Released, self::Completed, self::Resolved, self::ResolvedBuyer, self::ResolvedSeller => Heroicon::OutlinedCheckBadge,
            self::Disputed => Heroicon::OutlinedExclamationTriangle,
            self::Cancelled => Heroicon::OutlinedXCircle,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed, self::Cancelled, self::Expired,
            self::Resolved, self::ResolvedBuyer, self::ResolvedSeller,
        ], true);
    }

    public static function fromContractStatus(int $contractStatus): ?self
    {
        return match ($contractStatus) {
            1 => self::EscrowLocked,
            2 => self::PaymentSent,
            3 => self::Completed,
            4 => self::Disputed,
            5 => self::Cancelled,
            6 => self::Resolved,
            7 => self::SellFunded,
            default => null,
        };
    }
}
