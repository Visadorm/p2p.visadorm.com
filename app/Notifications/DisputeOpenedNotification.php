<?php

namespace App\Notifications;

use App\Enums\TradeType;
use App\Models\Trade;

class DisputeOpenedNotification extends MerchantNotification
{
    public function __construct(public Trade $trade, public string $reason = '') {}

    public function getType(): string
    {
        return 'dispute_opened';
    }

    public function getVariables(): array
    {
        return [
            'hash' => substr($this->trade->trade_hash, 0, 10) . '...',
            'amount' => $this->trade->amount_usdc,
            'reason' => str($this->reason)->limit(100),
            'merchant_name' => $this->trade->merchant->username,
        ];
    }

    protected function getTradeId(): ?int
    {
        return $this->trade->id;
    }

    protected function getActionUrl(): string
    {
        return $this->trade->type === TradeType::Sell
            ? url("/sell/trade/{$this->trade->trade_hash}")
            : url("/trade/{$this->trade->trade_hash}/release");
    }

    protected function getActionText(): string
    {
        return __('notifications.action.view_trade');
    }
}
