<?php

namespace App\Listeners;

use App\Events\KycDocumentReviewed;
use App\Services\MerchantBadgeService;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateMerchantBadgesOnKycReview implements ShouldQueue
{
    public function __construct(
        private MerchantBadgeService $badgeService,
    ) {}

    public function handle(KycDocumentReviewed $event): void
    {
        $merchant = $event->document->merchant;

        $this->badgeService->updateBadges($merchant);
    }
}
