<?php

namespace App\Console\Commands;

use App\Enums\TradeStatus;
use App\Models\Trade;
use App\Services\BlockchainService;
use Illuminate\Console\Command;
use App\Enums\TradingLinkType;
use Illuminate\Support\Facades\Log;

class RetryBlockchainConfirm extends Command
{
    protected $signature = 'trades:retry-blockchain';

    protected $description = 'Retry blockchain confirmPayment for completed trades with missing release_tx_hash';

    public function handle(): void
    {
        $blockchain = app(BlockchainService::class);

        $trades = Trade::where('status', TradeStatus::Completed)
            ->whereNull('release_tx_hash')
            ->where('completed_at', '>=', now()->subHours(48))
            ->get();

        if ($trades->isEmpty()) {
            $this->info('No stuck trades found.');
        } else {
            $this->info("Found {$trades->count()} stuck trade(s). Retrying...");
        }

        foreach ($trades as $trade) {
            try {
                $txHash = $blockchain->confirmPayment($trade->trade_hash);
                $trade->update(['release_tx_hash' => $txHash]);

                $this->info("Trade {$trade->trade_hash}: released (tx: {$txHash})");

                Log::info('Retry blockchain confirm succeeded', [
                    'trade_hash' => $trade->trade_hash,
                    'tx_hash' => $txHash,
                ]);
            } catch (\Throwable $e) {
                $this->error("Trade {$trade->trade_hash}: failed — {$e->getMessage()}");

                Log::error('Retry blockchain confirm failed', [
                    'trade_hash' => $trade->trade_hash,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Also retry Pending trades that should have been locked (job may have failed)
        $pendingTrades = Trade::where('status', TradeStatus::Pending)
            ->whereNull('escrow_tx_hash')
            ->where('created_at', '>=', now()->subHours(48))
            ->where('created_at', '<=', now()->subMinutes(5))
            ->get();

        if ($pendingTrades->isNotEmpty()) {
            $this->info("Found {$pendingTrades->count()} pending trade(s) without escrow. Re-dispatching...");

            foreach ($pendingTrades as $trade) {
                try {
                    $trade->loadMissing('merchant');
                    \App\Jobs\ProcessTradeInitiation::dispatch(
                        $trade,
                        $trade->merchant->wallet_address,
                        $trade->buyer_wallet,
                        $trade->tradingLink?->type === TradingLinkType::Private,
                    );
                    $this->info("Trade {$trade->trade_hash}: re-dispatched initiation job");
                    Log::info('Re-dispatched trade initiation', ['trade_hash' => $trade->trade_hash]);
                } catch (\Throwable $e) {
                    $this->error("Trade {$trade->trade_hash}: failed — {$e->getMessage()}");
                    Log::error('Failed to re-dispatch trade initiation', [
                        'trade_hash' => $trade->trade_hash,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
