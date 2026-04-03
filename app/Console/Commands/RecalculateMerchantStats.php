<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Services\MerchantRankService;
use Illuminate\Console\Command;

class RecalculateMerchantStats extends Command
{
    protected $signature = 'merchants:recalculate-stats';

    protected $description = 'Recalculate stats and ranks for all merchants (both buy and sell trades)';

    public function handle(MerchantRankService $rankService): int
    {
        // Fix stuck migration if column already exists
        $migrationName = '2026_04_04_000001_add_reviewer_role_to_reviews_table';
        $exists = \Illuminate\Support\Facades\DB::table('migrations')->where('migration', $migrationName)->exists();
        if (! $exists) {
            \Illuminate\Support\Facades\DB::table('migrations')->insert([
                'migration' => $migrationName,
                'batch' => \Illuminate\Support\Facades\DB::table('migrations')->max('batch') + 1,
            ]);
            $this->info("Marked migration {$migrationName} as complete.");
        }

        $merchants = Merchant::all();
        $this->info("Recalculating stats for {$merchants->count()} merchants...");

        foreach ($merchants as $merchant) {
            $stats = $merchant->allTrades()->selectRaw("
                COUNT(*) as total_trades,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_trades,
                SUM(CASE WHEN status = ? THEN amount_usdc ELSE 0 END) as total_volume,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as disputed_trades
            ", ['completed', 'completed', 'disputed'])->first();

            $total = (int) $stats->total_trades;
            $completed = (int) $stats->completed_trades;
            $volume = (float) $stats->total_volume;
            $disputed = (int) $stats->disputed_trades;

            $cr = $total > 0 ? round(($completed / $total) * 100, 2) : 0;
            $dr = $total > 0 ? round(($disputed / $total) * 100, 2) : 0;
            $rs = max(0, min(10, round(($cr / 10) - ($dr / 5), 1)));

            $merchant->update([
                'total_trades' => $total,
                'total_volume' => $volume,
                'completion_rate' => $cr,
                'dispute_rate' => $dr,
                'reliability_score' => $rs,
            ]);

            $rankService->updateMerchantRank($merchant);

            $rank = $merchant->fresh()->rank->name ?? 'N/A';
            $this->line("{$merchant->username}: {$total} trades, {$cr}% completion, \${$volume} — {$rank}");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
