<?php

namespace App\Listeners;

use App\Enums\TradeStatus;
use App\Events\TradeCompleted;
use App\Models\Merchant;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UpdateMerchantStatsOnTradeComplete implements ShouldQueue
{
    public function handle(TradeCompleted $event): void
    {
        $trade = $event->trade;
        $trade->loadMissing('merchant');

        DB::transaction(function () use ($trade) {
            $merchant = Merchant::where('id', $trade->merchant_id)->lockForUpdate()->first();

            // Upsert daily stats in a single query instead of 4
            DB::statement("
                INSERT INTO merchant_stats (merchant_id, `date`, trades_count, completed_count, volume, created_at)
                VALUES (?, ?, 1, 1, ?, ?)
                ON DUPLICATE KEY UPDATE
                    trades_count = trades_count + 1,
                    completed_count = completed_count + 1,
                    volume = volume + VALUES(volume)
            ", [$merchant->id, Carbon::today()->toDateString(), (float) $trade->amount_usdc, Carbon::now()]);

            // Recalculate merchant aggregate stats — count BOTH buy and sell trades
            $stats = $merchant->allTrades()->selectRaw("
                COUNT(*) as total_trades,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_trades,
                SUM(CASE WHEN status = ? THEN amount_usdc ELSE 0 END) as total_volume,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as disputed_trades
            ", [
                TradeStatus::Completed->value,
                TradeStatus::Completed->value,
                TradeStatus::Disputed->value,
            ])->first();

            $totalTrades = (int) $stats->total_trades;
            $completedTrades = (int) $stats->completed_trades;
            $totalVolume = (float) $stats->total_volume;
            $disputedTrades = (int) $stats->disputed_trades;

            $completionRate = $totalTrades > 0
                ? round(($completedTrades / $totalTrades) * 100, 2)
                : 0;

            $disputeRate = $totalTrades > 0
                ? round(($disputedTrades / $totalTrades) * 100, 2)
                : 0;

            $reliabilityScore = min(10, round(($completionRate / 10) - ($disputeRate / 5), 1));
            $reliabilityScore = max(0, $reliabilityScore);

            $merchant->update([
                'total_trades' => $totalTrades,
                'total_volume' => $totalVolume,
                'completion_rate' => $completionRate,
                'dispute_rate' => $disputeRate,
                'reliability_score' => $reliabilityScore,
            ]);
        });

        // Also update the buyer's merchant stats (if they have a merchant account)
        $buyerMerchant = Merchant::where('wallet_address', $trade->buyer_wallet)->first();
        if ($buyerMerchant && $buyerMerchant->id !== $trade->merchant_id) {
            DB::transaction(function () use ($buyerMerchant) {
                $bm = Merchant::where('id', $buyerMerchant->id)->lockForUpdate()->first();

                $stats = $bm->allTrades()->selectRaw("
                    COUNT(*) as total_trades,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_trades,
                    SUM(CASE WHEN status = ? THEN amount_usdc ELSE 0 END) as total_volume,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as disputed_trades
                ", [
                    TradeStatus::Completed->value,
                    TradeStatus::Completed->value,
                    TradeStatus::Disputed->value,
                ])->first();

                $totalTrades = (int) $stats->total_trades;
                $completedTrades = (int) $stats->completed_trades;
                $totalVolume = (float) $stats->total_volume;
                $disputedTrades = (int) $stats->disputed_trades;

                $completionRate = $totalTrades > 0
                    ? round(($completedTrades / $totalTrades) * 100, 2) : 0;
                $disputeRate = $totalTrades > 0
                    ? round(($disputedTrades / $totalTrades) * 100, 2) : 0;
                $reliabilityScore = max(0, min(10, round(($completionRate / 10) - ($disputeRate / 5), 1)));

                $bm->update([
                    'total_trades' => $totalTrades,
                    'total_volume' => $totalVolume,
                    'completion_rate' => $completionRate,
                    'dispute_rate' => $disputeRate,
                    'reliability_score' => $reliabilityScore,
                ]);
            });
        }

        Cache::forget('platform_stats');
    }
}
