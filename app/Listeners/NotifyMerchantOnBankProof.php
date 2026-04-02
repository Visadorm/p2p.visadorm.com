<?php
namespace App\Listeners;
use App\Events\BankProofUploaded;
use App\Notifications\BankProofUploadedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyMerchantOnBankProof implements ShouldQueue
{
    public function handle(BankProofUploaded $event): void
    {
        $trade = $event->trade;
        $trade->loadMissing('merchant');
        $trade->merchant->notify(new BankProofUploadedNotification($trade));
    }
}
