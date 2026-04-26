<?php
namespace App\Listeners;

use App\Events\ReviewSubmitted;
use App\Models\Merchant;
use App\Notifications\ReviewReceivedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyMerchantOnReviewReceived implements ShouldQueue
{
    public function handle(ReviewSubmitted $event): void
    {
        $review = $event->review;
        $review->loadMissing('merchant');
        $reviewedMerchant = $review->merchant ?? Merchant::find($review->merchant_id);

        if ($reviewedMerchant) {
            $reviewedMerchant->notify(new ReviewReceivedNotification($review));
        }
    }
}
