<?php
namespace App\Listeners;
use App\Events\PaymentMarked;
use App\Notifications\PaymentMarkedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyMerchantOnPaymentMarked implements ShouldQueue
{
    public function handle(PaymentMarked $event): void
    {
        $trade = $event->trade;
        $trade->loadMissing('merchant');
        $trade->merchant->notify(new PaymentMarkedNotification($trade));
    }
}
