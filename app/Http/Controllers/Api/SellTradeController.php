<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trade;
use App\Services\SellTradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SellTradeController extends Controller
{
    public function __construct(private readonly SellTradeService $sellTrades)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'merchant_wallet' => ['required', 'regex:/^0x[a-fA-F0-9]{40}$/'],
            'amount' => ['required', 'numeric', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'payment_method_id' => ['required', 'integer', 'exists:merchant_payment_methods,id'],
            'fiat_rate' => ['required', 'numeric', 'min:0.000001'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'is_cash_trade' => ['sometimes', 'boolean'],
            'meeting_location' => ['nullable', 'string', 'max:255'],
            'entry_path' => ['nullable', 'in:merchant_page,private_link'],
        ]);

        $merchant = \App\Models\Merchant::query()
            ->whereRaw('LOWER(wallet_address) = ?', [strtolower($validated['merchant_wallet'])])
            ->first();

        if (! $merchant) {
            return response()->json(['message' => __('p2p.merchant_not_found')], 404);
        }

        try {
            $payload = $this->sellTrades->openTrade($merchant, $validated, $request->merchant->wallet_address);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'trade_hash' => $payload['trade_hash'],
                'trade_id' => $payload['trade_id'],
                'calldata' => $payload['calldata'],
                'escrow_address' => $payload['escrow_address'],
                'approve_amount' => $payload['approve_amount'],
                'expires_at' => $payload['expires_at'],
                'stake_required' => $payload['stake_required'],
                'stake_amount_usdc' => $payload['stake_amount_usdc'],
            ],
            'message' => __('p2p.sell_trade_payload_built'),
        ], 201);
    }

    public function confirmFund(Request $request, string $tradeHash): JsonResponse
    {
        $validated = $request->validate([
            'fund_tx_hash' => ['required', 'regex:/^0x[a-fA-F0-9]{64}$/'],
        ]);

        $trade = $this->findTrade($tradeHash);
        if (! $trade) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        try {
            $trade = $this->sellTrades->confirmFund($trade, $validated['fund_tx_hash']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serialize($trade, $request),
            'message' => __('p2p.sell_trade_funded'),
        ]);
    }

    public function show(Request $request, string $tradeHash): JsonResponse
    {
        $trade = $this->findTrade($tradeHash);
        if (! $trade) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        return response()->json([
            'data' => $this->serialize($trade, $request),
            'message' => __('p2p.sell_trade_loaded'),
        ]);
    }

    public function confirmJoin(Request $request, string $tradeHash): JsonResponse
    {
        $validated = $request->validate([
            'join_tx_hash' => ['required', 'regex:/^0x[a-fA-F0-9]{64}$/'],
        ]);

        $trade = $this->findTrade($tradeHash);
        if (! $trade) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        try {
            $trade = $this->sellTrades->confirmJoin($trade, $request->merchant, $validated['join_tx_hash']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serialize($trade, $request),
            'message' => __('p2p.sell_trade_joined'),
        ]);
    }

    public function confirmMarkPaid(Request $request, string $tradeHash): JsonResponse
    {
        $validated = $request->validate([
            'mark_paid_tx_hash' => ['required', 'regex:/^0x[a-fA-F0-9]{64}$/'],
        ]);

        $trade = $this->findTrade($tradeHash);
        if (! $trade) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        try {
            $trade = $this->sellTrades->confirmMarkPaid($trade, $request->merchant, $validated['mark_paid_tx_hash']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serialize($trade, $request),
            'message' => __('p2p.sell_trade_payment_marked'),
        ]);
    }

    public function cashProof(Request $request, string $tradeHash): JsonResponse
    {
        $validated = $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $trade = $this->findTrade($tradeHash);
        if (! $trade) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        try {
            $trade = $this->sellTrades->attachCashProof($trade, $validated['proof'], $validated['note'] ?? null);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serialize($trade, $request),
            'message' => __('p2p.sell_trade_cash_proof_uploaded'),
        ]);
    }

    public function verifyPayment(Request $request, string $tradeHash): JsonResponse
    {
        $validated = $request->validate([
            'verified' => ['required', 'boolean'],
        ]);

        $trade = $this->findTrade($tradeHash);
        if (! $trade) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        try {
            $trade = $this->sellTrades->setSellerVerifiedPayment($trade, $request->merchant, $validated['verified']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serialize($trade, $request),
            'message' => __('p2p.sell_trade_payment_verified'),
        ]);
    }

    public function confirmRelease(Request $request, string $tradeHash): JsonResponse
    {
        $validated = $request->validate([
            'release_tx_hash' => ['required', 'regex:/^0x[a-fA-F0-9]{64}$/'],
        ]);

        $trade = $this->findTrade($tradeHash);
        if (! $trade) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        try {
            $trade = $this->sellTrades->confirmRelease($trade, $validated['release_tx_hash']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serialize($trade, $request),
            'message' => __('p2p.sell_trade_released'),
        ]);
    }

    public function openDispute(Request $request, string $tradeHash): JsonResponse
    {
        $validated = $request->validate([
            'dispute_tx_hash' => ['required', 'regex:/^0x[a-fA-F0-9]{64}$/'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $trade = $this->findTrade($tradeHash);
        if (! $trade) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        try {
            $trade = $this->sellTrades->openDispute($trade, $request->merchant, $validated['dispute_tx_hash'], $validated['reason']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serialize($trade, $request),
            'message' => __('p2p.sell_trade_dispute_opened'),
        ]);
    }

    public function cancel(Request $request, string $tradeHash): JsonResponse
    {
        $validated = $request->validate([
            'cancel_tx_hash' => ['required', 'regex:/^0x[a-fA-F0-9]{64}$/'],
        ]);

        $trade = $this->findTrade($tradeHash);
        if (! $trade) {
            return response()->json(['message' => __('p2p.sell_trade_not_found')], 404);
        }

        try {
            $trade = $this->sellTrades->cancel($trade, $validated['cancel_tx_hash']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serialize($trade, $request),
            'message' => __('p2p.sell_trade_cancelled'),
        ]);
    }

    private function findTrade(string $tradeHash): ?Trade
    {
        return Trade::query()
            ->where('trade_hash', $tradeHash)
            ->where('type', \App\Enums\TradeType::Sell)
            ->with(['merchant'])
            ->first();
    }

    private function serialize(Trade $trade, Request $request): array
    {
        $callerWallet = strtolower($request->merchant?->wallet_address ?? '');

        return [
            'trade_hash' => $trade->trade_hash,
            'type' => $trade->type->value,
            'status' => $trade->status->value,
            'seller_wallet' => $trade->seller_wallet,
            'buyer_wallet' => $trade->buyer_wallet,
            'merchant' => [
                'id' => $trade->merchant?->id,
                'username' => $trade->merchant?->username,
                'wallet_address' => $trade->merchant?->wallet_address,
            ],
            'amount_usdc' => $trade->amount_usdc,
            'amount_fiat' => $trade->amount_fiat,
            'currency_code' => $trade->currency_code,
            'exchange_rate' => $trade->exchange_rate,
            'fee_amount' => $trade->fee_amount,
            'payment_method' => $trade->payment_method,
            'is_cash_trade' => (bool) $trade->is_cash_trade,
            'cash_proof_url' => $trade->cash_proof_url,
            'seller_verified_payment' => (bool) $trade->seller_verified_payment,
            'fund_tx_hash' => $trade->fund_tx_hash,
            'join_tx_hash' => $trade->join_tx_hash,
            'mark_paid_tx_hash' => $trade->mark_paid_tx_hash,
            'release_tx_hash' => $trade->release_tx_hash,
            'cancel_tx_hash' => $trade->cancel_tx_hash,
            'dispute_tx_hash' => $trade->dispute_tx_hash,
            'expires_at' => $trade->expires_at?->toIso8601String(),
            'completed_at' => $trade->completed_at?->toIso8601String(),
            'is_seller' => $callerWallet !== '' && $callerWallet === strtolower((string) $trade->seller_wallet),
            'is_merchant' => $callerWallet !== '' && $callerWallet === strtolower((string) $trade->buyer_wallet),
            'stake_amount' => $trade->stake_amount,
        ];
    }
}
