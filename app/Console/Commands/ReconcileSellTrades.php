<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TradeStatus;
use App\Enums\TradeType;
use App\Events\TradeCompleted;
use App\Models\Trade;
use App\Services\BlockchainService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReconcileSellTrades extends Command
{
    protected $signature = 'trades:reconcile-sell
        {--limit=50 : Max trades to reconcile per run}';

    protected $description = 'Sync DB sell-trade status with on-chain status. Catches missed events.';

    public function handle(BlockchainService $blockchain): int
    {
        $limit = (int) $this->option('limit');

        $trades = Trade::query()
            ->where('type', TradeType::Sell)
            ->whereIn('status', [
                TradeStatus::EscrowLocked,
                TradeStatus::PaymentSent,
                TradeStatus::Released,
                TradeStatus::Disputed,
            ])
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        $synced = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($trades as $trade) {
            try {
                $onChain = $blockchain->getTradeOnChain('0x' . $this->stripHex($trade->trade_hash));
                $contractStatus = $onChain['status'] ?? null;
                if ($contractStatus === null) {
                    $skipped++;
                    continue;
                }

                $newStatus = TradeStatus::fromContractStatus((int) $contractStatus);
                if (! $newStatus || $newStatus === $trade->status) {
                    $skipped++;
                    continue;
                }

                $update = ['status' => $newStatus];
                if ($newStatus === TradeStatus::Completed && ! $trade->completed_at) {
                    $update['completed_at'] = now();
                }
                if ($newStatus === TradeStatus::Disputed && ! $trade->disputed_at) {
                    $update['disputed_at'] = now();
                }
                $trade->update($update);

                if ($newStatus === TradeStatus::Completed) {
                    TradeCompleted::dispatch($trade->fresh());
                }

                $synced++;
            } catch (Throwable $e) {
                $errors++;
                Log::warning('reconcile-sell error', [
                    'trade_hash' => $trade->trade_hash,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Reconciled: {$synced} | Skipped: {$skipped} | Errors: {$errors}");
        return self::SUCCESS;
    }

    private function stripHex(string $hex): string
    {
        return str_starts_with($hex, '0x') ? substr($hex, 2) : $hex;
    }
}
