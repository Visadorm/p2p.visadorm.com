<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TradeStatus;
use App\Enums\TradeType;
use App\Models\Trade;
use App\Services\BlockchainService;
use App\Settings\TradeSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('p2p:cancel-expired-sell-trades')]
#[Description('A9: cancel expired sell trades on-chain (Pending/EscrowLocked only) and refund stake/escrow.')]
class CancelExpiredSellTrades extends Command
{
    public function handle(TradeSettings $settings, BlockchainService $blockchain): int
    {
        if (! $settings->sell_auto_cancel_expired_enabled) {
            $this->info('Auto-cancel disabled in settings — skipping.');
            return self::SUCCESS;
        }

        // A9: After PaymentSent, timer must STOP. Only Pending and EscrowLocked
        // are auto-cancellable. Disputed handled by mediator council, not here.
        // fund_tx_hash filter excludes pre-fund DB-only rows (no on-chain escrow
        // exists yet, so cancelExpiredSellTrade would revert).
        $expired = Trade::query()
            ->where('type', TradeType::Sell)
            ->whereIn('status', [TradeStatus::Pending, TradeStatus::EscrowLocked])
            ->whereNotNull('fund_tx_hash')
            ->where('expires_at', '<', now())
            ->limit(50) // bounded per run; cron will pick up the rest next minute
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired sell trades.');
            return self::SUCCESS;
        }

        $this->info("Found {$expired->count()} expired sell trade(s). Broadcasting cancels…");

        foreach ($expired as $trade) {
            try {
                $txHash = $blockchain->cancelExpiredSellTrade($trade->trade_hash);

                $trade->update([
                    'cancel_tx_hash' => $txHash,
                    'status' => TradeStatus::Cancelled,
                ]);

                Log::info('Auto-cancelled expired sell trade', [
                    'trade_id' => $trade->id,
                    'trade_hash' => $trade->trade_hash,
                    'tx_hash' => $txHash,
                ]);
                $this->info("  ✓ {$trade->trade_hash} → {$txHash}");
            } catch (Throwable $e) {
                Log::error('Auto-cancel failed', [
                    'trade_id' => $trade->id,
                    'trade_hash' => $trade->trade_hash,
                    'error' => $e->getMessage(),
                ]);
                $this->warn("  ✗ {$trade->trade_hash} failed: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
