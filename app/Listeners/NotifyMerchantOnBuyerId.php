<?php
namespace App\Listeners;
use App\Events\BuyerIdSubmitted;
use App\Notifications\BuyerIdSubmittedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyMerchantOnBuyerId implements ShouldQueue
{
    public function handle(BuyerIdSubmitted $event): void
    {
        $trade = $event->trade;
        $trade->loadMissing('merchant');
        $trade->merchant->notify(new BuyerIdSubmittedNotification($trade));
    }
}
