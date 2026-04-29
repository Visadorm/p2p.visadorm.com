<?php
namespace App\Listeners;
use App\Enums\TradeType;
use App\Events\DisputeOpened;
use App\Models\Merchant;
use App\Notifications\DisputeOpenedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyMerchantOnDispute implements ShouldQueue
{
    public function handle(DisputeOpened $event): void
    {
        $dispute = $event->dispute;
        $dispute->loadMissing('trade.merchant');
        $trade = $dispute->trade;
        $reason = $dispute->reason ?? '';

        // Notify the merchant counterparty (always)
        $trade->merchant->notify(new DisputeOpenedNotification($trade, $reason));

        // Sell flow: also notify seller (lookup by wallet) since either party
        // can open dispute and both need to know.
        if ($trade->type === TradeType::Sell && ! empty($trade->seller_wallet)) {
            $seller = Merchant::query()
                ->whereRaw('LOWER(wallet_address) = ?', [strtolower($trade->seller_wallet)])
                ->first();
            if ($seller && $seller->id !== $trade->merchant->id) {
                $seller->notify(new DisputeOpenedNotification($trade, $reason));
            }
        }
    }
}
