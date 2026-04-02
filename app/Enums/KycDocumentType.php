<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum KycDocumentType: string implements HasColor, HasIcon, HasLabel
{
    case IdDocument = 'id_document';
    case BankStatement = 'bank_statement';
    case ProofOfResidency = 'proof_of_residency';
    case BusinessDocument = 'business_document';

    public function getLabel(): string
    {
        return match ($this) {
            self::IdDocument => __('kyc.type.id_document'),
            self::BankStatement => __('kyc.type.bank_statement'),
            self::ProofOfResidency => __('kyc.type.proof_of_residency'),
            self::BusinessDocument => __('kyc.type.business_document'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::IdDocument => 'primary',
            self::BankStatement => 'info',
            self::ProofOfResidency => 'warning',
            self::BusinessDocument => 'success',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::IdDocument => Heroicon::OutlinedIdentification,
            self::BankStatement => Heroicon::OutlinedDocumentText,
            self::ProofOfResidency => Heroicon::OutlinedHomeModern,
            self::BusinessDocument => Heroicon::OutlinedBriefcase,
        };
    }
}
