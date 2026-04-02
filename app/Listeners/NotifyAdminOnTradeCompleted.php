<?php
namespace App\Listeners;

use App\Events\TradeCompleted;
use App\Services\AdminNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyAdminOnTradeCompleted implements ShouldQueue
{
    public function handle(TradeCompleted $event): void
    {
        $trade = $event->trade;
        $trade->loadMissing('merchant');

        AdminNotificationService::notifyIf(
            'alert_large_trade', // Reuse large trade toggle for completed oversight
            'Trade Completed',
            "Trade for {$trade->amount_usdc} USDC completed. Merchant: {$trade->merchant->username}. Fee: {$trade->fee_amount} USDC.",
            'heroicon-o-check-circle',
            'success'
        );
    }
}
