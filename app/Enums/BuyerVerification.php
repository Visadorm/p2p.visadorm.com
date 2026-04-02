<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum BuyerVerification: string implements HasColor, HasIcon, HasLabel
{
    case Disabled = 'disabled';
    case Optional = 'optional';
    case Required = 'required';

    public function getLabel(): string
    {
        return match ($this) {
            self::Disabled => __('merchant.buyer_verification.disabled'),
            self::Optional => __('merchant.buyer_verification.optional'),
            self::Required => __('merchant.buyer_verification.required'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Disabled => 'gray',
            self::Optional => 'warning',
            self::Required => 'success',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Disabled => Heroicon::OutlinedEyeSlash,
            self::Optional => Heroicon::OutlinedQuestionMarkCircle,
            self::Required => Heroicon::OutlinedShieldCheck,
        };
    }
}
