<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\MerchantRank;
use Illuminate\Support\Facades\Cache;

class MerchantRankService
{
    /**
     * Calculate which rank a merchant qualifies for based on their stats.
     * Checks from Elite down to New Member. Legendary is an admin override.
     */
    public function calculateRank(Merchant $merchant): MerchantRank
    {
        if ($merchant->is_legendary) {
            return $this->getRankBySlug('legendary-merchant');
        }

        if (
            $merchant->total_trades >= 10000
            && $merchant->completion_rate >= 97
            && $merchant->total_volume >= 10_000_000
        ) {
            return $this->getRankBySlug('elite-merchant');
        }

        if (
            $merchant->total_trades >= 1000
            && $merchant->completion_rate >= 95
            && $merchant->total_volume >= 1_000_000
        ) {
            return $this->getRankBySlug('hero-merchant');
        }

        if (
            $merchant->total_trades >= 101
            && $merchant->completion_rate >= 90
        ) {
            return $this->getRankBySlug('senior-member');
        }

        if ($merchant->total_trades >= 21) {
            return $this->getRankBySlug('junior-member');
        }

        return $this->getRankBySlug('new-member');
    }

    /**
     * Calculate and persist the merchant rank.
     */
    public function updateMerchantRank(Merchant $merchant): void
    {
        $rank = $this->calculateRank($merchant);

        if ($merchant->rank_id !== $rank->id) {
            $merchant->update(['rank_id' => $rank->id]);
        }
    }

    private function getRankBySlug(string $slug): MerchantRank
    {
        $data = Cache::remember(
            'merchant_rank:' . $slug,
            3600,
            fn () => MerchantRank::where('slug', $slug)->firstOrFail()->toArray()
        );

        return (new MerchantRank)->forceFill($data);
    }
}
