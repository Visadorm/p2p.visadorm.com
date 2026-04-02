<?php
namespace App\Listeners;
use App\Events\TradeCompleted;
use App\Notifications\TradeCompletedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyMerchantOnTradeCompleted implements ShouldQueue
{
    public function handle(TradeCompleted $event): void
    {
        $trade = $event->trade;
        $trade->loadMissing('merchant');
        $trade->merchant->notify(new TradeCompletedNotification($trade));
    }
}
