<?php
namespace App\Listeners;
use App\Events\TradeInitiated;
use App\Notifications\TradeInitiatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyMerchantOnTradeInitiated implements ShouldQueue
{
    public function handle(TradeInitiated $event): void
    {
        $trade = $event->trade;
        $trade->loadMissing('merchant');
        $trade->merchant->notify(new TradeInitiatedNotification($trade));
    }
}
