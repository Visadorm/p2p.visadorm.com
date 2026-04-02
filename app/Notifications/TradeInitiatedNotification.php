<?php

namespace App\Notifications;

use App\Models\Trade;

class TradeInitiatedNotification extends MerchantNotification
{
    public function __construct(public Trade $trade) {}

    public function getType(): string
    {
        return 'trade_initiated';
    }

    public function getVariables(): array
    {
        return [
            'hash' => substr($this->trade->trade_hash, 0, 10) . '...',
            'amount' => $this->trade->amount_usdc,
            'currency' => $this->trade->currency_code,
            'merchant_name' => $this->trade->merchant->username,
            'buyer_wallet' => substr($this->trade->buyer_wallet ?? '', 0, 10) . '...',
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
        return __('notifications.action.view_trade');
    }
}
