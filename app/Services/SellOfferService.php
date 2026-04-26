<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Merchant;
use App\Models\SellOffer;
use App\Models\Trade;
use App\Settings\TradeSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class SellOfferService
{
    public function __construct(private readonly TradeSettings $tradeSettings) {}

    public function createOffer(Merchant $seller, array $data): SellOffer
    {
        $this->ensureSellEnabled();
        $this->ensureUnderWalletLimits($seller, (float) $data['amount_usdc']);
        $this->ensureKycIfRequired($seller, (float) $data['amount_usdc']);

        $paymentMethods = $this->resolvePaymentMethods($seller, $data['payment_methods']);

        $expiresAt = isset($data['expires_at'])
            ? Carbon::parse($data['expires_at'])
            : now()->addMinutes($this->tradeSettings->sell_default_offer_timer_minutes);

        return SellOffer::create([
            'slug' => $this->uniqueSlug(),
            'trade_id' => strtolower($data['trade_id']),
            'seller_wallet' => strtolower($seller->wallet_address),
            'seller_merchant_id' => $seller->id,
            'amount_usdc' => $data['amount_usdc'],
            'amount_remaining_usdc' => $data['amount_usdc'],
            'min_trade_usdc' => $data['amount_usdc'],
            'max_trade_usdc' => $data['amount_usdc'],
            'currency_code' => strtoupper($data['currency_code']),
            'fiat_rate' => $data['fiat_rate'],
            'payment_methods' => $paymentMethods,
            'instructions' => $data['instructions'] ?? null,
            'require_kyc' => (bool) ($data['require_kyc'] ?? false),
            'is_private' => (bool) ($data['is_private'] ?? false),
            'is_active' => true,
            'fund_tx_hash' => $data['fund_tx_hash'],
            'expires_at' => $expiresAt,
        ]);
    }

    private function resolvePaymentMethods(Merchant $seller, array $items): array
    {
        if (empty($items)) {
            throw new InvalidArgumentException('At least one payment method is required.');
        }

        $ids = collect($items)->pluck('merchant_payment_method_id')->filter()->unique()->values();
        if ($ids->isEmpty()) {
            throw new InvalidArgumentException('Each payment method must reference a saved method (merchant_payment_method_id).');
        }

        $owned = \App\Models\MerchantPaymentMethod::query()
            ->where('merchant_id', $seller->id)
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->get(['id', 'label', 'provider', 'type']);

        if ($owned->count() !== $ids->count()) {
            throw new InvalidArgumentException('One or more payment methods do not belong to you or are inactive.');
        }

        $cashMeeting = $owned->first(fn ($pm) => ($pm->type?->value ?? (string) $pm->type) === 'cash_meeting');
        if ($cashMeeting) {
            throw new InvalidArgumentException('Cash-meeting payment methods are not supported on sell offers yet.');
        }

        return $owned->map(fn ($pm) => [
            'merchant_payment_method_id' => (int) $pm->id,
            'label' => (string) $pm->label,
            'provider' => (string) $pm->provider,
            'type' => $pm->type?->value ?? (string) $pm->type,
        ])->values()->all();
    }

    public function cancelOffer(SellOffer $offer, Merchant $seller, ?string $cancelTxHash = null): SellOffer
    {
        if (strtolower($offer->seller_wallet) !== strtolower($seller->wallet_address)) {
            throw new InvalidArgumentException('Only the seller can cancel this offer.');
        }

        if (! $offer->is_active) {
            throw new RuntimeException('Offer already inactive.');
        }

        $hasOpenTrades = $offer->trades()
            ->whereNotIn('status', ['completed', 'cancelled', 'expired'])
            ->exists();

        if ($hasOpenTrades) {
            throw new RuntimeException('Cannot cancel an offer with active trades. Wait for them to settle.');
        }

        $offer->update([
            'is_active' => false,
            'cancel_tx_hash' => $cancelTxHash,
        ]);

        return $offer->fresh();
    }

    public function activeOffersForWallet(string $wallet): int
    {
        return SellOffer::query()
            ->forSellerWallet($wallet)
            ->where('is_active', true)
            ->count();
    }

    public function outstandingUsdcForWallet(string $wallet): float
    {
        return (float) SellOffer::query()
            ->forSellerWallet($wallet)
            ->where('is_active', true)
            ->sum('amount_remaining_usdc');
    }

    public function recentSellVolumeUsdc(string $wallet, int $windowDays): float
    {
        return (float) Trade::query()
            ->where('seller_wallet', strtolower($wallet))
            ->where('type', 'sell')
            ->where('status', 'completed')
            ->where('completed_at', '>=', now()->subDays($windowDays))
            ->sum('amount_usdc');
    }

    private function ensureSellEnabled(): void
    {
        if (! $this->tradeSettings->sell_enabled) {
            throw new RuntimeException('Sell flow is currently disabled.');
        }
    }

    private function ensureUnderWalletLimits(Merchant $seller, float $newAmount): void
    {
        $wallet = $seller->wallet_address;
        $activeCount = $this->activeOffersForWallet($wallet);

        if ($activeCount >= $this->tradeSettings->sell_max_offers_per_wallet) {
            throw new RuntimeException(__('p2p.sell_too_many_offers', [
                'max' => $this->tradeSettings->sell_max_offers_per_wallet,
            ]));
        }

        $outstanding = $this->outstandingUsdcForWallet($wallet) + $newAmount;
        if ($outstanding > $this->tradeSettings->sell_max_outstanding_usdc) {
            throw new RuntimeException(__('p2p.sell_outstanding_cap_exceeded', [
                'max' => $this->tradeSettings->sell_max_outstanding_usdc,
            ]));
        }
    }

    private function ensureKycIfRequired(Merchant $seller, float $newAmount): void
    {
        $threshold = $this->tradeSettings->sell_kyc_threshold_usdc;
        $window = $this->tradeSettings->sell_kyc_threshold_window_days;

        $recent = $this->recentSellVolumeUsdc($seller->wallet_address, $window);
        if (($recent + $newAmount) <= $threshold) {
            return;
        }

        if ($seller->kyc_status !== \App\Enums\KycStatus::Approved) {
            throw new RuntimeException(__('p2p.sell_kyc_required', [
                'amount' => number_format($threshold, 0),
                'days' => $window,
            ]));
        }
    }

    private function uniqueSlug(): string
    {
        do {
            $slug = strtolower(Str::random(12));
        } while (SellOffer::where('slug', $slug)->exists());
        return $slug;
    }
}
