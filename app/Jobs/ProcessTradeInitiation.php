<?php

namespace App\Jobs;

use App\Enums\TradeStatus;
use App\Models\Trade;
use App\Services\BlockchainService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTradeInitiation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public Trade $trade,
        public string $merchantWallet,
        public string $buyerWallet,
        public bool $isPrivate,
    ) {}

    public function handle(BlockchainService $blockchainService): void
    {
        $rawAmount = $blockchainService->humanToUsdc((string) $this->trade->amount_usdc);

        $txHash = $blockchainService->initiateTrade(
            tradeHash: $this->trade->trade_hash,
            merchant: $this->merchantWallet,
            buyer: $this->buyerWallet,
            amount: $rawAmount,
            isPrivate: $this->isPrivate,
            expiresAt: $this->trade->expires_at
                ? $this->trade->expires_at->timestamp
                : (time() + 3600),
        );

        $blockchainService->waitForReceipt($txHash);

        // Only update status if trade hasn't already advanced past Pending
        $this->trade->refresh();
        if ($this->trade->status === TradeStatus::Pending) {
            $this->trade->update([
                'status' => TradeStatus::EscrowLocked,
                'escrow_tx_hash' => $txHash,
            ]);
        } else {
            // Trade already advanced — just store the tx hash
            $this->trade->update(['escrow_tx_hash' => $txHash]);
        }

        $isCashMeeting = in_array(
            strtolower($this->trade->payment_method),
            ['cash_meeting', 'cash meeting']
        );

        if ($isCashMeeting) {
            $nftTxHash = $blockchainService->mintTradeNFT(
                $this->trade->trade_hash,
                $this->trade->meeting_location ?? 'TBD'
            );

            $receipt = $blockchainService->waitForReceipt($nftTxHash);
            $tokenId = $blockchainService->parseNftTokenIdFromReceipt($receipt);

            $this->trade->update(['nft_token_id' => $tokenId]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessTradeInitiation permanently failed', [
            'trade_hash' => $this->trade->trade_hash,
            'error' => $exception->getMessage(),
        ]);
    }
}
