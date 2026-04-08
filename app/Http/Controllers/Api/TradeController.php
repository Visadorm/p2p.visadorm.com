<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\TradeStatus;
use App\Enums\TradeType;
use App\Models\MerchantTradingLink;
use App\Models\Trade;
use App\Services\ExchangeRateService;
use App\Services\TradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradeController extends Controller
{
    public function __construct(
        private readonly TradeService $tradeService,
        private readonly ExchangeRateService $exchangeRateService,
    ) {}

    /**
     * PUBLIC: Show trading link details for trade initiation page.
     */
    public function show(string $slug): JsonResponse
    {
        $link = MerchantTradingLink::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $link) {
            return response()->json([
                'message' => __('p2p.trading_link_not_found'),
            ], 404);
        }

        $merchant = $link->merchant;

        if (! $merchant || ! $merchant->is_active) {
            return response()->json([
                'message' => __('p2p.merchant_not_active'),
            ], 404);
        }

        $merchant->load(['rank', 'currencies' => function ($query) {
            $query->where('is_active', true);
        }, 'paymentMethods' => function ($query) {
            $query->where('is_active', true);
        }]);

        $escrowService = app(\App\Services\EscrowService::class);
        $escrowBalance = $escrowService->getMerchantAvailableBalance($merchant);

        $currencies = $merchant->currencies->map(fn ($c) => [
            'id' => $c->id,
            'currency_code' => $c->currency_code,
            'markup_percent' => $c->markup_percent,
            'market_rate' => rescue(fn () => $this->exchangeRateService->getRate($c->currency_code), 0),
            'min_amount' => $c->min_amount,
            'max_amount' => $c->max_amount,
        ]);

        return response()->json([
            'data' => [
                'trading_link' => $link,
                'merchant' => [
                    'username' => $merchant->username,
                    'wallet_address' => $merchant->wallet_address,
                    'rank' => $merchant->rank,
                    'total_trades' => $merchant->total_trades,
                    'completion_rate' => $merchant->completion_rate,
                    'avg_response_minutes' => $merchant->avg_response_minutes,
                    'buyer_verification' => $merchant->buyer_verification,
                    'trade_timer_minutes' => $merchant->trade_timer_minutes,
                    'trade_instructions' => $merchant->trade_instructions,
                ],
                'currencies' => $currencies,
                'payment_methods' => $merchant->paymentMethods,
                'escrow_balance' => $escrowBalance,
            ],
            'message' => __('p2p.trade_link_loaded'),
        ]);
    }

    /**
     * Initiate a new trade from a trading link.
     */
    public function initiate(Request $request, string $slug): JsonResponse
    {
        $validated = $request->validate([
            'amount_usdc' => ['required', 'numeric', 'min:1'],
            'currency_code' => ['required', 'string', 'max:3'],
            'payment_method' => ['required', 'string', 'max:100'],
            'escrow_tx_hash' => ['sometimes', 'nullable', 'string', 'max:255'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);

        $isDryRun = $request->boolean('dry_run');

        $link = MerchantTradingLink::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $link) {
            return response()->json([
                'message' => __('p2p.trading_link_not_found'),
            ], 404);
        }

        $merchant = $link->merchant;

        // Validate currency is supported by this merchant
        $currencyCode = strtoupper($validated['currency_code']);
        $merchantCurrency = $merchant->currencies()
            ->where('currency_code', $currencyCode)
            ->where('is_active', true)
            ->first();

        if (! $merchantCurrency) {
            return response()->json([
                'message' => __('trade.error.unsupported_currency', ['currency' => $currencyCode]),
            ], 422);
        }

        // Validate payment method is offered by this merchant
        $hasPaymentMethod = $merchant->paymentMethods()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('id', $validated['payment_method'])
                ->orWhere('provider', $validated['payment_method'])
                ->orWhere('label', $validated['payment_method'])
                ->orWhere('type', $validated['payment_method']))
            ->exists();

        if (! $hasPaymentMethod) {
            return response()->json([
                'message' => __('trade.error.unsupported_payment_method'),
            ], 422);
        }

        // Enforce buyer verification if merchant requires it
        if ($merchant->buyer_verification === \App\Enums\BuyerVerification::Required) {
            $buyerWallet = $request->user()->wallet_address;
            $buyerMerchant = \App\Models\Merchant::where('wallet_address', $buyerWallet)->first();
            $hasKyc = $buyerMerchant && \App\Models\KycDocument::where('merchant_id', $buyerMerchant->id)
                ->where('status', \App\Enums\KycStatus::Approved)
                ->exists();

            if (! $hasKyc) {
                return response()->json([
                    'message' => __('trade.error.buyer_verification_required'),
                ], 422);
            }
        }

        // Prevent self-trading (wash trading)
        $buyerWalletAddress = strtolower($request->user()->wallet_address);
        if ($buyerWalletAddress === strtolower($merchant->wallet_address)) {
            return response()->json([
                'message' => __('trade.error.cannot_self_trade'),
            ], 422);
        }

        // Prevent buyer from having multiple active trades with same merchant
        $hasActiveTrade = Trade::where('merchant_id', $merchant->id)
            ->whereRaw('LOWER(buyer_wallet) = ?', [$buyerWalletAddress])
            ->whereIn('status', [
                TradeStatus::Pending,
                TradeStatus::EscrowLocked,
                TradeStatus::PaymentSent,
                TradeStatus::Disputed,
            ])
            ->exists();

        if ($hasActiveTrade) {
            return response()->json([
                'message' => __('trade.error.active_trade_exists'),
            ], 422);
        }

        // Calculate exchange rate (before dry_run so errors are caught in pre-check)
        try {
            $exchangeRate = $this->exchangeRateService->getRate($validated['currency_code']);
            $amountFiat = $this->exchangeRateService->convert((float) $validated['amount_usdc'], $validated['currency_code']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => __('trade.error.exchange_rate_unavailable'),
            ], 422);
        }

        // Check merchant has enough escrow balance
        $canTrade = rescue(fn () => $this->tradeService->getEscrowService()->canInitiateTrade($merchant, (float) $validated['amount_usdc']), true);
        if (! $canTrade) {
            return response()->json([
                'message' => __('trade.error.merchant_insufficient_escrow'),
            ], 422);
        }

        // Dry run: all checks passed, return OK without creating trade
        if ($isDryRun) {
            return response()->json([
                'message' => __('p2p.trade_checks_passed'),
            ]);
        }

        $isPrivate = $link->type === \App\Enums\TradingLinkType::Private;

        // Auto-fill meeting location from merchant's cash meeting payment method
        $meetingLocation = null;
        $paymentMethodRecord = $merchant->paymentMethods()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('id', $validated['payment_method'])
                ->orWhere('provider', $validated['payment_method'])
                ->orWhere('label', $validated['payment_method'])
                ->orWhere('type', $validated['payment_method']))
            ->first();

        if ($paymentMethodRecord?->location) {
            $meetingPoint = $paymentMethodRecord->details['meeting_point'] ?? null;
            $meetingLocation = $meetingPoint
                ? $paymentMethodRecord->location . ' — ' . $meetingPoint
                : $paymentMethodRecord->location;
        }

        $trade = $this->tradeService->initiateTrade($merchant, [
            'amount_usdc' => $validated['amount_usdc'],
            'amount_fiat' => $amountFiat,
            'exchange_rate' => $exchangeRate,
            'currency_code' => $validated['currency_code'],
            'payment_method' => $paymentMethodRecord?->label ?? $paymentMethodRecord?->provider ?? $validated['payment_method'],
            'buyer_wallet' => $request->user()->wallet_address,
            'trading_link_id' => $link->id,
            'type' => TradeType::Buy,
            'stake_amount' => $isPrivate ? 0 : null,
            'stake_paid_by' => $isPrivate ? null : \App\Enums\StakePaidBy::Buyer,
            'escrow_tx_hash' => $validated['escrow_tx_hash'] ?? null,
            'meeting_location' => $meetingLocation,
            'meeting_lat' => $paymentMethodRecord?->location_lat,
            'meeting_lng' => $paymentMethodRecord?->location_lng,
        ]);

        // Dispatch async blockchain job (escrow lock + NFT mint)
        \App\Jobs\ProcessTradeInitiation::dispatch(
            $trade,
            $merchant->wallet_address,
            $request->user()->wallet_address,
            $isPrivate,
        );

        return response()->json([
            'data' => $trade->fresh(),
            'message' => __('p2p.trade_initiated'),
        ], 201);
    }

    /**
     * Public trade verification (no auth required).
     */
    public function verify(string $tradeHash): JsonResponse
    {
        $trade = Trade::where('trade_hash', $tradeHash)
            ->with('merchant:id,username,wallet_address')
            ->first();

        if (! $trade) {
            return response()->json([
                'message' => __('p2p.trade_not_found'),
            ], 404);
        }

        return response()->json([
            'data' => [
                'trade_hash' => $trade->trade_hash,
                'amount_usdc' => $trade->amount_usdc,
                'amount_fiat' => $trade->amount_fiat,
                'currency_code' => $trade->currency_code,
                'buyer_wallet' => $trade->buyer_wallet,
                'merchant_name' => $trade->merchant?->username,
                'payment_method' => $trade->payment_method,
                'meeting_location' => $trade->meeting_location,
                'nft_token_id' => $trade->nft_token_id,
                'status' => $trade->status,
                'created_at' => $trade->created_at,
            ],
        ]);
    }

    /**
     * Get trade status.
     */
    public function status(Request $request, string $tradeHash): JsonResponse
    {
        $trade = Trade::where('trade_hash', $tradeHash)
            ->with(['merchant:id,username,wallet_address,rank_id,full_name,business_name,kyc_status', 'merchant.rank', 'tradingLink', 'review', 'merchantReview', 'dispute'])
            ->first();

        if (! $trade) {
            return response()->json([
                'message' => __('p2p.trade_not_found'),
            ], 404);
        }

        $userWallet = strtolower($request->user()->wallet_address);
        $isBuyer = strtolower($trade->buyer_wallet) === $userWallet;
        $isMerchant = strtolower($trade->merchant->wallet_address) === $userWallet;

        if (! $isBuyer && ! $isMerchant) {
            return response()->json([
                'message' => __('p2p.trade_not_authorized'),
            ], 403);
        }

        $data = $trade->toArray();

        // Include merchant's payment method details so buyer knows WHERE to send fiat
        $paymentMethod = $trade->merchant->paymentMethods()
            ->where(function ($q) use ($trade) {
                $q->where('provider', $trade->payment_method)
                  ->orWhere('label', $trade->payment_method);
            })
            ->first();

        $data['payment_method_details'] = $paymentMethod?->details;
        $data['payment_method_label'] = $paymentMethod?->label;
        $data['safety_note'] = $paymentMethod?->safety_note;

        // Show masked merchant identity for verified merchants (seller → buyer)
        $m = $trade->merchant;
        if ($m->kyc_status?->value === 'approved' && $m->full_name) {
            $parts = explode(' ', $m->full_name, 2);
            $first = $parts[0] ?? '';
            $last = isset($parts[1]) ? strtoupper(substr($parts[1], 0, 1)) . str_repeat('*', max(0, strlen($parts[1]) - 1)) : '';
            $data['merchant_verified_name'] = trim($first . ' ' . $last);
            if ($m->business_name) {
                $data['merchant_business_name'] = $m->business_name;
            }
        }

        // Show masked buyer identity for verified buyers (buyer → seller)
        if ($isMerchant) {
            $buyerMerchant = \App\Models\Merchant::where('wallet_address', $trade->buyer_wallet)->first();
            if ($buyerMerchant && $buyerMerchant->kyc_status?->value === 'approved' && $buyerMerchant->full_name) {
                $parts = explode(' ', $buyerMerchant->full_name, 2);
                $first = $parts[0] ?? '';
                $last = isset($parts[1]) ? strtoupper(substr($parts[1], 0, 1)) . str_repeat('*', max(0, strlen($parts[1]) - 1)) : '';
                $data['buyer_verified_name'] = trim($first . ' ' . $last);
                if ($buyerMerchant->business_name) {
                    $data['buyer_business_name'] = $buyerMerchant->business_name;
                }
            }
        }

        return response()->json([
            'data' => $data,
            'message' => __('p2p.trade_status_loaded'),
        ]);
    }

    /**
     * Buyer marks payment as sent.
     */
    public function markPaid(Request $request, string $tradeHash): JsonResponse
    {
        $trade = Trade::where('trade_hash', $tradeHash)->first();

        if (! $trade) {
            return response()->json([
                'message' => __('p2p.trade_not_found'),
            ], 404);
        }

        if (strtolower($trade->buyer_wallet) !== strtolower($request->user()->wallet_address)) {
            return response()->json([
                'message' => __('p2p.trade_not_authorized'),
            ], 403);
        }

        if (! in_array($trade->status, [TradeStatus::Pending, TradeStatus::EscrowLocked])) {
            return response()->json([
                'message' => __('p2p.trade_invalid_status'),
            ], 422);
        }

        $this->tradeService->markPaymentSent($trade);

        \App\Jobs\ProcessTradeBlockchainSync::dispatch($trade, 'mark_paid');

        return response()->json([
            'data' => $trade->fresh(),
            'message' => __('p2p.trade_marked_paid'),
        ]);
    }

    /**
     * Buyer uploads bank proof.
     */
    public function uploadBankProof(Request $request, string $tradeHash): JsonResponse
    {
        $request->validate([
            'bank_proof' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $trade = Trade::where('trade_hash', $tradeHash)->first();

        if (! $trade) {
            return response()->json([
                'message' => __('p2p.trade_not_found'),
            ], 404);
        }

        if (strtolower($trade->buyer_wallet) !== strtolower($request->user()->wallet_address)) {
            return response()->json([
                'message' => __('p2p.trade_not_authorized'),
            ], 403);
        }

        if (! in_array($trade->status, [TradeStatus::EscrowLocked, TradeStatus::PaymentSent])) {
            return response()->json([
                'message' => __('p2p.trade_invalid_status'),
            ], 422);
        }

        $trade = $this->tradeService->uploadBankProof($trade, $request->file('bank_proof'));

        return response()->json([
            'data' => $trade,
            'message' => __('p2p.trade_bank_proof_uploaded'),
        ]);
    }

    /**
     * Buyer uploads ID document.
     */
    public function uploadBuyerId(Request $request, string $tradeHash): JsonResponse
    {
        $request->validate([
            'buyer_id' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $trade = Trade::where('trade_hash', $tradeHash)->first();

        if (! $trade) {
            return response()->json([
                'message' => __('p2p.trade_not_found'),
            ], 404);
        }

        if (strtolower($trade->buyer_wallet) !== strtolower($request->user()->wallet_address)) {
            return response()->json([
                'message' => __('p2p.trade_not_authorized'),
            ], 403);
        }

        if (! in_array($trade->status, [TradeStatus::EscrowLocked, TradeStatus::PaymentSent])) {
            return response()->json([
                'message' => __('p2p.trade_invalid_status'),
            ], 422);
        }

        $trade = $this->tradeService->uploadBuyerId($trade, $request->file('buyer_id'));

        return response()->json([
            'data' => $trade,
            'message' => __('p2p.trade_buyer_id_uploaded'),
        ]);
    }

    /**
     * Buyer cancels a trade.
     */
    public function cancel(Request $request, string $tradeHash): JsonResponse
    {
        $trade = Trade::where('trade_hash', $tradeHash)->first();

        if (! $trade) {
            return response()->json([
                'message' => __('p2p.trade_not_found'),
            ], 404);
        }

        if (strtolower($trade->buyer_wallet) !== strtolower($request->user()->wallet_address)) {
            return response()->json([
                'message' => __('p2p.trade_not_authorized'),
            ], 403);
        }

        if (! in_array($trade->status, [TradeStatus::Pending, TradeStatus::EscrowLocked])) {
            return response()->json([
                'message' => __('p2p.trade_invalid_status'),
            ], 422);
        }

        $this->tradeService->cancelTrade($trade);

        if ($trade->escrow_tx_hash) {
            \App\Jobs\ProcessTradeBlockchainSync::dispatch($trade, 'cancel');
        }

        return response()->json([
            'data' => $trade->fresh(),
            'message' => __('p2p.trade_cancelled'),
        ]);
    }

    /**
     * Merchant confirms payment received and releases escrow.
     */
    public function confirm(Request $request, string $tradeHash): JsonResponse
    {
        $trade = Trade::where('trade_hash', $tradeHash)->first();

        if (! $trade) {
            return response()->json([
                'message' => __('p2p.trade_not_found'),
            ], 404);
        }

        $merchant = $request->merchant;

        if ($trade->merchant_id !== $merchant->id) {
            return response()->json([
                'message' => __('p2p.trade_not_authorized'),
            ], 403);
        }

        $isCashMeeting = in_array(strtolower($trade->payment_method), ['cash_meeting', 'cash meeting']);
        $allowedStatuses = $isCashMeeting
            ? [TradeStatus::Pending, TradeStatus::EscrowLocked, TradeStatus::PaymentSent]
            : [TradeStatus::PaymentSent];

        if (! in_array($trade->status, $allowedStatuses)) {
            return response()->json([
                'message' => __('p2p.trade_invalid_status'),
            ], 422);
        }

        if ($trade->expires_at && $trade->expires_at->isPast()) {
            return response()->json([
                'message' => __('trade.error.trade_expired'),
            ], 422);
        }

        $this->tradeService->confirmPayment($trade);

        // Dispatch async blockchain job (escrow release + NFT burn)
        \App\Jobs\ProcessTradeConfirmation::dispatch($trade);

        return response()->json([
            'data' => $trade->fresh(),
            'message' => __('p2p.trade_confirmed'),
        ]);
    }
}
