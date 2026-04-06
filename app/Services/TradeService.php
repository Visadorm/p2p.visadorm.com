<?php

namespace App\Services;

use App\Enums\BankProofStatus;
use App\Enums\TradeStatus;
use App\Events\BankProofUploaded;
use App\Events\BuyerIdSubmitted;
use App\Events\TradeCancelled;
use App\Events\TradeCompleted;
use App\Events\TradeInitiated;
use App\Events\PaymentMarked;
use App\Models\Merchant;
use App\Models\Trade;
use App\Settings\TradeSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TradeService
{
    public function __construct(
        private readonly EscrowService $escrowService,
    ) {}

    public function getEscrowService(): EscrowService
    {
        return $this->escrowService;
    }

    /**
     * Create a new trade record after validating escrow availability.
     */
    public function initiateTrade(Merchant $merchant, array $data): Trade
    {
        $amountUsdc = (float) $data['amount_usdc'];

        $trade = DB::transaction(function () use ($merchant, $data, $amountUsdc) {
            // Lock the merchant row to prevent concurrent trades from overcommitting escrow
            Merchant::where('id', $merchant->id)->lockForUpdate()->first();

            if (! $this->escrowService->canInitiateTrade($merchant, $amountUsdc)) {
                throw new \RuntimeException(__('trade.error.insufficient_escrow'));
            }

            $settings = app(TradeSettings::class);
            $timerMinutes = $merchant->trade_timer_minutes ?? $settings->default_trade_timer_minutes;
            $feeAmount = round($amountUsdc * 0.002, 6);

            return Trade::create([
                'trade_hash' => '0x' . bin2hex(random_bytes(32)),
                'trading_link_id' => $data['trading_link_id'] ?? null,
                'merchant_id' => $merchant->id,
                'buyer_wallet' => $data['buyer_wallet'],
                'amount_usdc' => $amountUsdc,
                'amount_fiat' => $data['amount_fiat'],
                'currency_code' => $data['currency_code'],
                'exchange_rate' => $data['exchange_rate'],
                'fee_amount' => $feeAmount,
                'payment_method' => $data['payment_method'],
                'type' => $data['type'],
                'status' => TradeStatus::Pending,
                'stake_amount' => $data['stake_amount'] ?? $settings->stake_amount,
                'stake_paid_by' => $data['stake_paid_by'] ?? null,
                'meeting_location' => $data['meeting_location'] ?? null,
                'meeting_lat' => $data['meeting_lat'] ?? null,
                'meeting_lng' => $data['meeting_lng'] ?? null,
                'expires_at' => now()->addMinutes($timerMinutes),
            ]);
        }, 3);

        TradeInitiated::dispatch($trade);

        Log::info('Trade initiated', [
            'trade_hash' => $trade->trade_hash,
            'merchant' => $merchant->id,
            'amount' => $amountUsdc,
        ]);

        return $trade;
    }

    /**
     * Mark a trade as payment sent by the buyer.
     */
    public function markPaymentSent(Trade $trade): void
    {
        $trade->update(['status' => TradeStatus::PaymentSent]);

        PaymentMarked::dispatch($trade);

        Log::info('Trade payment sent', ['trade_hash' => $trade->trade_hash]);
    }

    /**
     * Confirm payment received and complete the trade.
     * Stats and rank updates are handled by queued listeners.
     */
    public function confirmPayment(Trade $trade): void
    {
        $trade->update([
            'status' => TradeStatus::Completed,
            'completed_at' => now(),
        ]);

        TradeCompleted::dispatch($trade);

        Log::info('Trade confirmed', ['trade_hash' => $trade->trade_hash]);
    }

    /**
     * Cancel a trade.
     */
    public function cancelTrade(Trade $trade): void
    {
        $trade->update(['status' => TradeStatus::Cancelled]);

        TradeCancelled::dispatch($trade);

        Log::info('Trade cancelled', ['trade_hash' => $trade->trade_hash]);
    }

    /**
     * Upload bank proof for a trade and dispatch event.
     */
    public function uploadBankProof(Trade $trade, UploadedFile $file): Trade
    {
        $path = $file->store('trades/' . $trade->id . '/bank-proofs', 'local');

        $trade->update([
            'bank_proof_path' => $path,
            'bank_proof_status' => BankProofStatus::Pending,
        ]);

        BankProofUploaded::dispatch($trade);

        return $trade->fresh();
    }

    /**
     * Upload buyer ID document for a trade and dispatch event.
     */
    public function uploadBuyerId(Trade $trade, UploadedFile $file): Trade
    {
        $path = $file->store('trades/' . $trade->id . '/buyer-ids', 'local');

        $trade->update([
            'buyer_id_path' => $path,
            'buyer_id_status' => BankProofStatus::Pending,
        ]);

        BuyerIdSubmitted::dispatch($trade);

        return $trade->fresh();
    }

    /**
     * Find and expire stale trades past their expires_at.
     * Returns the count of expired trades.
     */
    public function expireStale(): int
    {
        $count = 0;
        $blockchainService = app(\App\Services\BlockchainService::class);

        Trade::whereIn('status', [
            TradeStatus::Pending,
            TradeStatus::EscrowLocked,
        ])
            ->where('expires_at', '<', now())
            ->chunkById(100, function ($trades) use (&$count, $blockchainService) {
                foreach ($trades as $trade) {
                    $trade->update(['status' => TradeStatus::Expired]);

                    // Notify merchant
                    $trade->loadMissing('merchant');
                    if ($trade->merchant) {
                        $trade->merchant->notify(new \App\Notifications\TradeExpiredNotification($trade));
                    }

                    // Settle on-chain: unlock merchant escrow + handle stake
                    if ($trade->escrow_tx_hash) {
                        try {
                            $blockchainService->cancelTrade($trade->trade_hash);
                        } catch (\Throwable $e) {
                            Log::error('Expire trade blockchain error', [
                                'trade_hash' => $trade->trade_hash,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    Log::info('Trade expired', ['trade_hash' => $trade->trade_hash]);
                }

                $count += $trades->count();
            });

        return $count;
    }
}
