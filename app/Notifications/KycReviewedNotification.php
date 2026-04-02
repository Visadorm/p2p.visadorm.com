<?php

namespace App\Notifications;

use App\Models\KycDocument;

class KycReviewedNotification extends MerchantNotification
{
    public function __construct(public KycDocument $document) {}

    public function getType(): string
    {
        return 'kyc_reviewed';
    }

    public function getVariables(): array
    {
        return [
            'document_type' => $this->document->type->value,
            'status' => $this->document->status->value === 'approved' ? 'approved' : 'rejected',
            'reason' => $this->document->rejection_reason ?? '',
            'merchant_name' => $this->document->merchant->username ?? '',
        ];
    }

    protected function getTradeId(): ?int
    {
        return null;
    }

    protected function getActionUrl(): string
    {
        return url('/kyc');
    }

    protected function getActionText(): string
    {
        return __('notifications.action.view_kyc');
    }
}
