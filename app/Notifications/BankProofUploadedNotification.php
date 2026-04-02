<?php

namespace App\Notifications;

use App\Models\Trade;

class BankProofUploadedNotification extends MerchantNotification
{
    public function __construct(public Trade $trade) {}

    public function via(object $notifiable): array
    {
        // Respect notify_bank_proof preference
        if (! $notifiable->notify_bank_proof) {
            return [];
        }

        return parent::via($notifiable);
    }

    public function getType(): string
    {
        return 'bank_proof_uploaded';
    }

    public function getVariables(): array
    {
        return [
            'hash' => substr($this->trade->trade_hash, 0, 10) . '...',
            'amount' => $this->trade->amount_usdc,
            'merchant_name' => $this->trade->merchant->username,
        ];
    }

    protected function getTradeId(): ?int
    {
        return $this->trade->id;
    }

    protected function getActionUrl(): string
    {
        return url("/trade/{$this->trade->trade_hash}/release");
    }

    protected function getActionText(): string
    {
        return __('notifications.action.view_proof');
    }
}
