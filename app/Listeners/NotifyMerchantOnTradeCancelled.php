<?php
namespace App\Listeners;
use App\Events\TradeCancelled;
use App\Notifications\TradeCancelledNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyMerchantOnTradeCancelled implements ShouldQueue
{
    public function handle(TradeCancelled $event): void
    {
        $trade = $event->trade;
        $trade->loadMissing('merchant');
        $trade->merchant->notify(new TradeCancelledNotification($trade));
    }
}
