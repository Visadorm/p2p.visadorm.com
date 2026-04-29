<?php
namespace App\Listeners;
use App\Enums\TradeType;
use App\Events\PaymentMarked;
use App\Models\Merchant;
use App\Notifications\PaymentMarkedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyMerchantOnPaymentMarked implements ShouldQueue
{
    public function handle(PaymentMarked $event): void
    {
        $trade = $event->trade;
        $trade->loadMissing('merchant');

        // Sell flow: trade.merchant IS the buyer-of-USDC who clicked "I Paid".
        // The party that needs the notification is the SELLER (trade.seller_wallet).
        if ($trade->type === TradeType::Sell && ! empty($trade->seller_wallet)) {
            $seller = Merchant::query()
                ->whereRaw('LOWER(wallet_address) = ?', [strtolower($trade->seller_wallet)])
                ->first();
            if ($seller) {
                $seller->notify(new PaymentMarkedNotification($trade));
            }
            return;
        }

        // Buy flow (default): notify the merchant (USDC seller)
        $trade->merchant->notify(new PaymentMarkedNotification($trade));
    }
}
