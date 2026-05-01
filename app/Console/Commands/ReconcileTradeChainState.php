<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TradeStatus;
use App\Enums\TradeType;
use App\Models\Trade;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('p2p:reconcile-trade-chain-state {--minutes=10}')]
#[Description('B3/B4: detect trades whose DB status diverges from on-chain reality. Logs warnings for admin review.')]
class ReconcileTradeChainState extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subMinutes((int) $this->option('minutes'));

        $orphanedCompleted = Trade::query()
            ->where('type', TradeType::Buy)
            ->where('status', TradeStatus::Completed)
            ->whereNull('release_tx_hash')
            ->where('completed_at', '<', $cutoff)
            ->limit(200)
            ->get(['id', 'trade_hash', 'completed_at', 'escrow_tx_hash']);

        $orphanedCancelled = Trade::query()
            ->where('type', TradeType::Buy)
            ->where('status', TradeStatus::Cancelled)
            ->whereNull('cancel_tx_hash')
            ->whereNotNull('escrow_tx_hash')
            ->where('updated_at', '<', $cutoff)
            ->limit(200)
            ->get(['id', 'trade_hash', 'updated_at', 'escrow_tx_hash']);

        $stuckConfirming = Trade::query()
            ->where('type', TradeType::Buy)
            ->where('status', TradeStatus::Confirming)
            ->where('updated_at', '<', $cutoff)
            ->limit(200)
            ->get(['id', 'trade_hash', 'updated_at']);

        $stuckCancelling = Trade::query()
            ->where('type', TradeType::Buy)
            ->where('status', TradeStatus::Cancelling)
            ->where('updated_at', '<', $cutoff)
            ->limit(200)
            ->get(['id', 'trade_hash', 'updated_at']);

        $totalCompleted = $orphanedCompleted->count();
        $totalCancelled = $orphanedCancelled->count();
        $totalStuckConfirming = $stuckConfirming->count();
        $totalStuckCancelling = $stuckCancelling->count();

        if ($totalCompleted === 0 && $totalCancelled === 0 && $totalStuckConfirming === 0 && $totalStuckCancelling === 0) {
            $this->info('No orphaned trades.');
            return self::SUCCESS;
        }

        if ($totalStuckConfirming > 0) {
            Log::warning('Stuck Confirming trades — confirmation job may have failed', [
                'count' => $totalStuckConfirming,
                'trade_hashes' => $stuckConfirming->pluck('trade_hash')->all(),
            ]);
            $this->warn("⚠ {$totalStuckConfirming} buy trade(s) stuck in Confirming:");
            foreach ($stuckConfirming as $t) {
                $this->line("  - {$t->trade_hash} (since {$t->updated_at})");
            }
        }

        if ($totalStuckCancelling > 0) {
            Log::warning('Stuck Cancelling trades — cancel job may have failed', [
                'count' => $totalStuckCancelling,
                'trade_hashes' => $stuckCancelling->pluck('trade_hash')->all(),
            ]);
            $this->warn("⚠ {$totalStuckCancelling} buy trade(s) stuck in Cancelling:");
            foreach ($stuckCancelling as $t) {
                $this->line("  - {$t->trade_hash} (since {$t->updated_at})");
            }
        }

        if ($totalCompleted > 0) {
            Log::warning('Orphaned Completed trades — DB completed but no release_tx_hash', [
                'count' => $totalCompleted,
                'trade_hashes' => $orphanedCompleted->pluck('trade_hash')->all(),
            ]);
            $this->warn("⚠ {$totalCompleted} buy trade(s) Completed without release_tx_hash:");
            foreach ($orphanedCompleted as $t) {
                $this->line("  - {$t->trade_hash} (completed_at={$t->completed_at})");
            }
        }

        if ($totalCancelled > 0) {
            Log::warning('Orphaned Cancelled trades — DB cancelled but no cancel_tx_hash', [
                'count' => $totalCancelled,
                'trade_hashes' => $orphanedCancelled->pluck('trade_hash')->all(),
            ]);
            $this->warn("⚠ {$totalCancelled} buy trade(s) Cancelled without cancel_tx_hash:");
            foreach ($orphanedCancelled as $t) {
                $this->line("  - {$t->trade_hash}");
            }
        }

        return self::SUCCESS;
    }
}
