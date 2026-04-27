<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SellTradeJoined;
use App\Models\Merchant;
use App\Notifications\SellTradeJoinedNotification;

class NotifySellerOnSellTradeJoined
{
    public function handle(SellTradeJoined $event): void
    {
        if (empty($event->trade->seller_wallet)) {
            return;
        }

        $seller = Merchant::query()
            ->whereRaw('LOWER(wallet_address) = ?', [strtolower($event->trade->seller_wallet)])
            ->first();

        if ($seller) {
            $seller->notify(new SellTradeJoinedNotification($event->trade));
        }
    }
}
