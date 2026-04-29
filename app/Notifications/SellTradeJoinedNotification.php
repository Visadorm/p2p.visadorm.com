<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Trade;

class SellTradeJoinedNotification extends MerchantNotification
{
    public function __construct(public Trade $trade)
    {
    }

    public function getType(): string
    {
        // Reuse existing trade_initiated template (generic enough for sell-join too).
        // Future: add dedicated 'sell_trade_joined' template row for tailored copy.
        return 'trade_initiated';
    }

    public function getVariables(): array
    {
        return [
            'hash' => substr($this->trade->trade_hash, 0, 10) . '...',
            'amount' => $this->trade->amount_usdc,
            'currency' => $this->trade->currency_code,
            'merchant_name' => $this->trade->merchant?->username ?? 'Merchant',
            'buyer_wallet' => substr($this->trade->buyer_wallet ?? '', 0, 10) . '...',
        ];
    }

    protected function getTradeId(): ?int
    {
        return $this->trade->id;
    }

    protected function getActionUrl(): string
    {
        return url("/sell/trade/{$this->trade->trade_hash}");
    }

    protected function getActionText(): string
    {
        return __('notifications.action.view_trade');
    }
}
