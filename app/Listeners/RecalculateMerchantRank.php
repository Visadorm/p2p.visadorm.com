<?php

namespace App\Listeners;

use App\Events\TradeCompleted;
use App\Services\MerchantRankService;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecalculateMerchantRank implements ShouldQueue
{
    public function __construct(
        private MerchantRankService $rankService,
    ) {}

    public function handle(TradeCompleted $event): void
    {
        $merchant = $event->trade->merchant;

        $this->rankService->updateMerchantRank($merchant);
    }
}
