<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Sell\BuildReleaseTypedData;
use App\Enums\KycStatus;
use App\Enums\TradeStatus;
use App\Enums\TradeType;
use App\Events\PaymentMarked;
use App\Events\TradeCompleted;
use App\Events\TradeInitiated;
use App\Models\Merchant;
use App\Models\SellOffer;
use App\Models\Trade;
use App\Settings\TradeSettings;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class SellTradeService
{
    public function __construct(
        private readonly BlockchainService $blockchain,
        private readonly BuildReleaseTypedData $typedDataBuilder,
        private readonly TradeSettings $tradeSettings,
        private readonly DisputeService $disputes,
    ) {}

    public function takeOffer(SellOffer $offer, Merchant $buyer, array $data): Trade
    {
        if (! $offer->is_active) {
            throw new RuntimeException('Offer no longer available.');
        }
        if ($offer->expires_at && $offer->expires_at->isPast()) {
            throw new RuntimeException('Offer expired.');
        }
        if (strtolower($offer->seller_wallet) === strtolower($buyer->wallet_address)) {
            throw new InvalidArgumentException('Seller cannot take own offer.');
        }
        if ($offer->require_kyc && $buyer->kyc_status !== KycStatus::Approved) {
            throw new RuntimeException(__('p2p.sell_buyer_kyc_required'));
        }

        $amountUsdc = (float) $offer->amount_usdc;
        $amountFiat = $amountUsdc * (float) $offer->fiat_rate;

        $snapshot = $this->snapshotChosenPaymentMethod($offer, $data['merchant_payment_method_id'] ?? null);

        $trade = DB::transaction(function () use ($offer, $buyer, $amountUsdc, $amountFiat, $snapshot, $data) {
            $trade = Trade::create([
                'trade_hash' => strtolower($offer->trade_id),
                'sell_offer_id' => $offer->id,
                'merchant_id' => $offer->seller_merchant_id,
                'seller_wallet' => strtolower($offer->seller_wallet),
                'buyer_wallet' => strtolower($buyer->wallet_address),
                'amount_usdc' => $amountUsdc,
                'amount_fiat' => $amountFiat,
                'currency_code' => $offer->currency_code,
                'exchange_rate' => $offer->fiat_rate,
                'fee_amount' => $amountUsdc * 0.002,
                'payment_method' => $snapshot['label'] ?? null,
                'seller_payment_snapshot' => $snapshot,
                'escrow_tx_hash' => $data['take_tx_hash'] ?? null,
                'type' => TradeType::Sell,
                'status' => TradeStatus::EscrowLocked,
                'expires_at' => now()->addMinutes($this->tradeSettings->default_trade_timer_minutes),
            ]);

            $offer->update([
                'amount_remaining_usdc' => 0,
                'is_active' => false,
            ]);

            return $trade;
        });

        TradeInitiated::dispatch($trade);

        return $trade;
    }

    private function snapshotChosenPaymentMethod(SellOffer $offer, ?int $chosenId): array
    {
        $offered = collect($offer->payment_methods ?? []);
        $chosenId = $chosenId ?: (int) $offered->pluck('merchant_payment_method_id')->filter()->first();

        if (! $chosenId) {
            throw new InvalidArgumentException('Pick a payment method to use for this trade.');
        }

        if (! $offered->contains('merchant_payment_method_id', $chosenId)) {
            throw new InvalidArgumentException('Selected payment method is not accepted by this offer.');
        }

        $pm = \App\Models\MerchantPaymentMethod::query()
            ->where('id', $chosenId)
            ->where('merchant_id', $offer->seller_merchant_id)
            ->first();

        if (! $pm) {
            throw new RuntimeException('Seller payment method missing — they may have removed it. Try again or pick another method.');
        }

        return [
            'merchant_payment_method_id' => (int) $pm->id,
            'label' => (string) $pm->label,
            'provider' => (string) $pm->provider,
            'type' => $pm->type?->value ?? (string) $pm->type,
            'details' => $pm->details ? (array) $pm->details : [],
            'safety_note' => $pm->safety_note,
            'logo_url' => $pm->logo_url,
        ];
    }

    public function markPaymentSent(Trade $trade, Merchant $caller, ?string $paidTxHash = null): Trade
    {
        $this->ensureSellTrade($trade);
        if (strtolower($trade->buyer_wallet) !== strtolower($caller->wallet_address)) {
            throw new InvalidArgumentException('Only the buyer can mark payment sent.');
        }
        if ($trade->status !== TradeStatus::EscrowLocked) {
            throw new RuntimeException('Trade not in EscrowLocked state.');
        }

        $trade->update(['status' => TradeStatus::PaymentSent]);
        $trade = $trade->fresh();

        PaymentMarked::dispatch($trade);

        return $trade;
    }

    public function buildReleasePayload(Trade $trade, Merchant $seller): array
    {
        $this->ensureSellTrade($trade);
        $this->ensureSellerCaller($trade, $seller);
        if (! in_array($trade->status, [TradeStatus::EscrowLocked, TradeStatus::PaymentSent], true)) {
            throw new RuntimeException('Trade not in a releasable state.');
        }

        $nonce = $this->blockchain->getSellerNonce($seller->wallet_address);
        return $this->typedDataBuilder->execute($trade, $nonce);
    }

    public function relayRelease(Trade $trade, Merchant $seller, array $signatureData): Trade
    {
        $this->ensureSellTrade($trade);
        $this->ensureSellerCaller($trade, $seller);
        if (! in_array($trade->status, [TradeStatus::EscrowLocked, TradeStatus::PaymentSent], true)) {
            throw new RuntimeException('Trade not in a releasable state.');
        }

        $nonce = (int) $signatureData['nonce'];
        $deadline = (int) $signatureData['deadline'];
        $sig = $signatureData['signature'];

        $txHash = $this->blockchain->executeMetaSellRelease(
            $trade->trade_hash,
            $nonce,
            $deadline,
            $sig,
        );

        $trade->update([
            'release_signature' => $sig,
            'release_signature_nonce' => $nonce,
            'release_signature_deadline' => now()->createFromTimestamp($deadline),
            'release_tx_hash' => $txHash,
            'status' => TradeStatus::Released,
        ]);

        $trade = $trade->fresh();

        return $trade;
    }

    public function openDispute(Trade $trade, Merchant $caller, ?string $reason = null): Trade
    {
        $this->ensureSellTrade($trade);
        $callerWallet = strtolower($caller->wallet_address);
        if ($callerWallet !== strtolower($trade->seller_wallet)
            && $callerWallet !== strtolower($trade->buyer_wallet)) {
            throw new InvalidArgumentException('Only seller or buyer can open dispute.');
        }
        if (! in_array($trade->status, [TradeStatus::EscrowLocked, TradeStatus::PaymentSent], true)) {
            throw new RuntimeException('Trade not in a disputable state.');
        }

        $this->disputes->openDispute($trade, $callerWallet, $reason ?? 'Sell trade dispute');

        return $trade->fresh();
    }

    private function ensureSellTrade(Trade $trade): void
    {
        if ($trade->type !== TradeType::Sell) {
            throw new InvalidArgumentException('Not a sell trade.');
        }
    }

    private function ensureSellerCaller(Trade $trade, Merchant $caller): void
    {
        if (strtolower($trade->seller_wallet) !== strtolower($caller->wallet_address)) {
            throw new InvalidArgumentException('Only the seller can perform this action.');
        }
    }
}
