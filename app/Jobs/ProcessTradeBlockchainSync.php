<?php

namespace App\Jobs;

use App\Models\Trade;
use App\Services\BlockchainService;
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

    public function handle(BlockchainService $blockchain): void
    {
        // Skip if trade not yet on-chain (initiation job hasn't completed)
        $this->trade->refresh();
        if (! $this->trade->escrow_tx_hash) {
            Log::warning("Trade {$this->trade->trade_hash}: skipping {$this->action} — not yet on-chain");
            $this->release(60); // retry in 60 seconds
            return;
        }

        $txHash = match ($this->action) {
            'mark_paid' => $blockchain->markPaymentSent($this->trade->trade_hash),
            'cancel' => $blockchain->cancelTrade($this->trade->trade_hash),
            default => throw new \InvalidArgumentException("Unknown action: {$this->action}"),
        };

        $column = $this->action === 'cancel' ? 'release_tx_hash' : 'escrow_tx_hash';
        $this->trade->update([$column => $txHash]);

        // Burn NFT on cancel if cash meeting trade with an existing NFT — failure is non-fatal
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
        Log::error("Trade blockchain {$this->action} failed permanently", [
            'trade_hash' => $this->trade->trade_hash,
            'error' => $e->getMessage(),
        ]);
    }
}
