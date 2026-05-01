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

class ProcessTradeConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public Trade $trade,
    ) {}

    public function handle(BlockchainService $blockchain, TradeService $trades): void
    {
        $this->trade->refresh();
        if (! $this->trade->escrow_tx_hash) {
            Log::warning("Trade {$this->trade->trade_hash}: skipping confirm — not yet on-chain");
            $this->release(60);
            return;
        }

        $txHash = $blockchain->confirmPayment($this->trade->trade_hash);

        $trades->finalizeConfirmedPayment($this->trade, $txHash);

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
        $this->trade->refresh();
        if ($this->trade->status === TradeStatus::Confirming) {
            $this->trade->update(['status' => TradeStatus::PaymentSent]);
        }

        Log::error('Trade blockchain confirmation failed permanently', [
            'trade_hash' => $this->trade->trade_hash,
            'error' => $e->getMessage(),
            'reverted_status_to' => 'PaymentSent',
        ]);
    }
}
