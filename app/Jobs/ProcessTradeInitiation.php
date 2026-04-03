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
        // Skip if trade already has escrow tx (already initiated on-chain)
        $this->trade->refresh();
        if ($this->trade->escrow_tx_hash) {
            Log::info("Trade {$this->trade->trade_hash}: already on-chain, skipping initiation");
            return;
        }

        // Skip if trade moved past pending (cancelled, completed, etc.)
        if (! in_array($this->trade->status, [TradeStatus::Pending])) {
            Log::info("Trade {$this->trade->trade_hash}: status is {$this->trade->status->value}, skipping initiation");
            return;
        }

        $rawAmount = $blockchainService->humanToUsdc((string) $this->trade->amount_usdc);

        try {
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
        } catch (\Throwable $e) {
            // If "Trade already exists" on-chain, mark as locked and stop retrying
            if (str_contains($e->getMessage(), 'Trade already exists')) {
                Log::info("Trade {$this->trade->trade_hash}: already exists on-chain, marking EscrowLocked");
                $this->trade->refresh();
                if ($this->trade->status === TradeStatus::Pending) {
                    $this->trade->update(['status' => TradeStatus::EscrowLocked]);
                }
                return;
            }
            throw $e;
        }

        $blockchainService->waitForReceipt($txHash);

        // Only set EscrowLocked if trade is still Pending (user may have acted while tx was mining)
        $this->trade->refresh();
        $updateData = ['escrow_tx_hash' => $txHash];
        if ($this->trade->status === TradeStatus::Pending) {
            $updateData['status'] = TradeStatus::EscrowLocked;
        }
        $this->trade->update($updateData);

        $isCashMeeting = in_array(
            strtolower($this->trade->payment_method),
            ['cash_meeting', 'cash meeting']
        );

        if ($isCashMeeting) {
            try {
                $nftTxHash = $blockchainService->mintTradeNFT(
                    $this->trade->trade_hash,
                    $this->trade->meeting_location ?? 'TBD'
                );

                $receipt = $blockchainService->waitForReceipt($nftTxHash);
                $tokenId = $blockchainService->parseNftTokenIdFromReceipt($receipt);

                $this->trade->update(['nft_token_id' => $tokenId]);
            } catch (\Throwable $e) {
                Log::error('NFT mint failed (non-fatal)', [
                    'trade_hash' => $this->trade->trade_hash,
                    'error' => $e->getMessage(),
                ]);
            }
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
