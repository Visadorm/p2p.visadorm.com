<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum PaymentMethodType: string implements HasColor, HasIcon, HasLabel
{
    case BankTransfer = 'bank_transfer';
    case OnlinePayment = 'online_payment';
    case MobilePayment = 'mobile_payment';
    case CashMeeting = 'cash_meeting';

    public function getLabel(): string
    {
        return match ($this) {
            self::BankTransfer => __('merchant.payment_type.bank_transfer'),
            self::OnlinePayment => __('merchant.payment_type.online_payment'),
            self::MobilePayment => __('merchant.payment_type.mobile_payment'),
            self::CashMeeting => __('merchant.payment_type.cash_meeting'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::BankTransfer => 'info',
            self::OnlinePayment => 'primary',
            self::MobilePayment => 'warning',
            self::CashMeeting => 'success',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::BankTransfer => Heroicon::OutlinedBuildingLibrary,
            self::OnlinePayment => Heroicon::OutlinedGlobeAlt,
            self::MobilePayment => Heroicon::OutlinedDevicePhoneMobile,
            self::CashMeeting => Heroicon::OutlinedMapPin,
        };
    }
}
