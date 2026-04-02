<?php
namespace App\Listeners;
use App\Events\DisputeOpened;
use App\Notifications\DisputeOpenedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyMerchantOnDispute implements ShouldQueue
{
    public function handle(DisputeOpened $event): void
    {
        $dispute = $event->dispute;
        $dispute->loadMissing('trade.merchant');
        $trade = $dispute->trade;
        $trade->merchant->notify(new DisputeOpenedNotification($trade, $dispute->reason ?? ''));
    }
}
