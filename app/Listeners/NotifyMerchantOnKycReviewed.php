<?php
namespace App\Listeners;
use App\Events\KycDocumentReviewed;
use App\Notifications\KycReviewedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyMerchantOnKycReviewed implements ShouldQueue
{
    public function handle(KycDocumentReviewed $event): void
    {
        $document = $event->document;
        $document->loadMissing('merchant');
        $document->merchant->notify(new KycReviewedNotification($document));
    }
}
