<?php

namespace App\Notifications;

use App\Enums\TradeType;
use App\Models\Trade;

class TradeInitiatedNotification extends MerchantNotification
{
    public function __construct(public Trade $trade) {}

    public function getType(): string
    {
        // Reuse 'trade_initiated' template for both buy + sell (template is generic).
        // Action URL/text below distinguish the two flows.
        return 'trade_initiated';
    }

    public function getVariables(): array
    {
        $isSell = $this->trade->type === TradeType::Sell;

        return [
            'hash' => substr($this->trade->trade_hash, 0, 10) . '...',
            'amount' => $this->trade->amount_usdc,
            'currency' => $this->trade->currency_code,
            'merchant_name' => $this->trade->merchant->username,
            'buyer_wallet' => substr($this->trade->buyer_wallet ?? '', 0, 10) . '...',
            'seller_wallet' => substr($this->trade->seller_wallet ?? '', 0, 10) . '...',
            'counterparty_wallet' => $isSell
                ? substr($this->trade->seller_wallet ?? '', 0, 10) . '...'
                : substr($this->trade->buyer_wallet ?? '', 0, 10) . '...',
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
        return $this->trade->type === TradeType::Sell
            ? __('notifications.action.accept_sell_trade')
            : __('notifications.action.view_trade');
    }
}
