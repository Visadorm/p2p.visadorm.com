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

class ProcessTradeConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public Trade $trade,
    ) {}

    public function handle(BlockchainService $blockchain): void
    {
        $txHash = $blockchain->confirmPayment($this->trade->trade_hash);
        $this->trade->update(['release_tx_hash' => $txHash]);

        // Burn NFT if cash meeting trade with an existing NFT — failure is non-fatal
        if (in_array(strtolower($this->trade->payment_method), ['cash_meeting', 'cash meeting']) && $this->trade->nft_token_id) {
            try {
                $blockchain->burnTradeNFT($this->trade->trade_hash);
            } catch (\Throwable $nftErr) {
                Log::error('burnTradeNFT error', [
                    'trade_hash' => $this->trade->trade_hash,
                    'error' => $nftErr->getMessage(),
                ]);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Trade blockchain confirmation failed permanently', [
            'trade_hash' => $this->trade->trade_hash,
            'error' => $e->getMessage(),
        ]);
    }
}
