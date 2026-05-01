<?php

namespace App\Jobs;

use App\Enums\TradeStatus;
use App\Models\Trade;
use App\Services\BlockchainService;
use App\Services\TradeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTradeBlockchainSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public Trade $trade,
        public string $action,
    ) {}

    public function handle(BlockchainService $blockchain, TradeService $trades): void
    {
        $this->trade->refresh();
        if (! $this->trade->escrow_tx_hash) {
            Log::warning("Trade {$this->trade->trade_hash}: skipping {$this->action} — not yet on-chain");
            $this->release(60);
            return;
        }

        $txHash = match ($this->action) {
            'mark_paid' => $blockchain->markPaymentSent($this->trade->trade_hash),
            'cancel' => $blockchain->cancelTrade($this->trade->trade_hash),
            default => throw new \InvalidArgumentException("Unknown action: {$this->action}"),
        };

        if ($this->action === 'cancel') {
            $trades->finalizeCancelledTrade($this->trade, $txHash);
        } else {
            $column = match ($this->action) {
                'mark_paid' => 'mark_paid_tx_hash',
                default => 'escrow_tx_hash',
            };
            $this->trade->update([$column => $txHash]);
        }

        if ($this->action === 'cancel'
            && in_array(strtolower($this->trade->payment_method), ['cash_meeting', 'cash meeting'])
            && $this->trade->nft_token_id
        ) {
            try {
                $blockchain->burnTradeNFT($this->trade->trade_hash);
            } catch (\Throwable $nftErr) {
                Log::error('burnTradeNFT error on cancel', [
                    'trade_hash' => $this->trade->trade_hash,
                    'error' => $nftErr->getMessage(),
                ]);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        if ($this->action === 'cancel') {
            $this->trade->refresh();
            if ($this->trade->status === TradeStatus::Cancelling) {
                $this->trade->update(['status' => TradeStatus::EscrowLocked]);
            }
            Log::error("Trade blockchain cancel failed permanently — reverted to EscrowLocked", [
                'trade_hash' => $this->trade->trade_hash,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        Log::error("Trade blockchain {$this->action} failed permanently", [
            'trade_hash' => $this->trade->trade_hash,
            'error' => $e->getMessage(),
        ]);
    }
}
