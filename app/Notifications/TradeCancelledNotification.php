<?php

namespace App\Notifications;

use App\Models\Trade;

class TradeCancelledNotification extends MerchantNotification
{
    public function __construct(public Trade $trade) {}

    public function getType(): string
    {
        return 'trade_cancelled';
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
        return url('/trades');
    }

    protected function getActionText(): string
    {
        return __('notifications.action.view_trades');
    }
}
