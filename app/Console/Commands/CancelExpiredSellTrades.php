<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TradeStatus;
use App\Enums\TradeType;
use App\Events\TradeCancelled;
use App\Models\SellOffer;
use App\Models\Trade;
use App\Services\BlockchainService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CancelExpiredSellTrades extends Command
{
    protected $signature = 'trades:cancel-expired-sell
        {--limit=20 : Max trades to cancel per run}
        {--dry-run : Print actions without sending tx}';

    protected $description = 'Permissionless cancel of expired sell trades. Operator pays gas.';

    public function handle(BlockchainService $blockchain): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $trades = Trade::query()
            ->where('type', TradeType::Sell)
            ->whereIn('status', [TradeStatus::SellFunded, TradeStatus::EscrowLocked])
            ->where('expires_at', '<', now())
            ->orderBy('expires_at')
            ->limit($limit)
            ->get();

        if ($trades->isEmpty()) {
            $this->info('No expired sell trades.');
            return self::SUCCESS;
        }

        $cancelled = 0;
        $errors = 0;

        foreach ($trades as $trade) {
            $this->line("Trade {$trade->trade_hash} expired at {$trade->expires_at}");

            if ($dryRun) {
                continue;
            }

            try {
                $txHash = $blockchain->cancelExpiredSellTrade($trade->trade_hash);
                $trade->update([
                    'status' => TradeStatus::Cancelled,
                    'release_tx_hash' => $txHash,
                ]);
                TradeCancelled::dispatch($trade->fresh());
                $cancelled++;
            } catch (Throwable $e) {
                $errors++;
                Log::warning('cancel-expired-sell failed', [
                    'trade_hash' => $trade->trade_hash,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->markExpiredOffers();

        $this->info("Cancelled: {$cancelled} | Errors: {$errors}");
        return self::SUCCESS;
    }

    private function markExpiredOffers(): void
    {
        SellOffer::query()
            ->where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['is_active' => false]);
    }
}
