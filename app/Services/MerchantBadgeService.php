<?php

namespace App\Services;

use App\Enums\KycDocumentType;
use App\Enums\KycStatus;
use App\Models\Merchant;
use App\Settings\TradeSettings;

class MerchantBadgeService
{
    /**
     * Recalculate all badge flags for a merchant based on their current data.
     */
    public function updateBadges(Merchant $merchant): void
    {
        $settings = app(TradeSettings::class);

        $merchant->update([
            'is_fast_responder' => $this->isFastResponder($merchant, $settings),
            'has_liquidity' => $this->hasLiquidity($merchant, $settings),
            'bank_verified' => $this->hasBankVerified($merchant),
            'email_verified' => $this->hasEmailVerified($merchant),
            'business_verified' => $this->hasBusinessVerified($merchant),
            'kyc_status' => $this->resolveKycStatus($merchant),
        ]);
    }

    /**
     * Fast Responder: average response time below configured threshold.
     */
    private function isFastResponder(Merchant $merchant, TradeSettings $settings): bool
    {
        if ($merchant->avg_response_minutes === null) {
            return false;
        }

        return $merchant->avg_response_minutes < $settings->fast_responder_minutes;
    }

    /**
     * Liquidity: total volume above configured threshold.
     */
    private function hasLiquidity(Merchant $merchant, TradeSettings $settings): bool
    {
        return (float) $merchant->total_volume > $settings->liquidity_badge_threshold;
    }

    /**
     * Bank Verified: has an approved bank_statement KYC document.
     */
    private function hasBankVerified(Merchant $merchant): bool
    {
        return $merchant->kycDocuments()
            ->where('type', KycDocumentType::BankStatement)
            ->where('status', KycStatus::Approved)
            ->exists();
    }

    /**
     * Email Verified: email address is set.
     */
    private function hasEmailVerified(Merchant $merchant): bool
    {
        return $merchant->email !== null;
    }

    /**
     * Business Verified: has an approved business_document KYC document.
     */
    private function hasBusinessVerified(Merchant $merchant): bool
    {
        return $merchant->kycDocuments()
            ->where('type', KycDocumentType::BusinessDocument)
            ->where('status', KycStatus::Approved)
            ->exists();
    }

    /**
     * KYC Status: approved if has approved id_document, pending/rejected otherwise.
     */
    private function resolveKycStatus(Merchant $merchant): KycStatus
    {
        $idDocument = $merchant->kycDocuments()
            ->where('type', KycDocumentType::IdDocument)
            ->latest()
            ->first();

        if (! $idDocument) {
            return KycStatus::Pending;
        }

        return $idDocument->status;
    }
}
