<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SellOffer;
use App\Models\Trade;
use App\Services\SellTradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SellTradeController extends Controller
{
    public function __construct(private readonly SellTradeService $sellTrades) {}

    public function take(Request $request, string $slug): JsonResponse
    {
        $offer = SellOffer::query()->where('slug', $slug)->first();
        if (! $offer) {
            return response()->json(['message' => __('p2p.sell_offer_not_found')], 404);
        }

        $validated = $request->validate([
            'merchant_payment_method_id' => ['required', 'integer'],
            'take_tx_hash' => ['required', 'regex:/^0x[a-fA-F0-9]{64}$/'],
        ]);

        try {
            $trade = $this->sellTrades->takeOffer($offer, $request->merchant, $validated);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $trade,
            'message' => __('p2p.sell_trade_taken'),
        ], 201);
    }

    public function markPaid(Request $request, string $tradeHash): JsonResponse
    {
        $trade = Trade::where('trade_hash', $tradeHash)->first();
        if (! $trade) {
            return response()->json(['message' => __('p2p.trade_not_found')], 404);
        }

        $validated = $request->validate([
            'paid_tx_hash' => ['nullable', 'regex:/^0x[a-fA-F0-9]{64}$/'],
        ]);

        try {
            $trade = $this->sellTrades->markPaymentSent($trade, $request->merchant, $validated['paid_tx_hash'] ?? null);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $trade,
            'message' => __('p2p.sell_trade_payment_sent'),
        ]);
    }

    public function releasePayload(Request $request, string $tradeHash): JsonResponse
    {
        $trade = Trade::where('trade_hash', $tradeHash)->first();
        if (! $trade) {
            return response()->json(['message' => __('p2p.trade_not_found')], 404);
        }

        try {
            $payload = $this->sellTrades->buildReleasePayload($trade, $request->merchant);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $payload,
            'message' => __('p2p.sell_trade_release_payload_built'),
        ]);
    }

    public function release(Request $request, string $tradeHash): JsonResponse
    {
        $trade = Trade::where('trade_hash', $tradeHash)->first();
        if (! $trade) {
            return response()->json(['message' => __('p2p.trade_not_found')], 404);
        }

        $validated = $request->validate([
            'signature' => ['required', 'regex:/^0x[a-fA-F0-9]{130}$/'],
            'nonce' => ['required', 'integer', 'min:0'],
            'deadline' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $trade = $this->sellTrades->relayRelease($trade, $request->merchant, $validated);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $trade,
            'message' => __('p2p.sell_trade_released'),
        ]);
    }

    public function dispute(Request $request, string $tradeHash): JsonResponse
    {
        $trade = Trade::where('trade_hash', $tradeHash)->first();
        if (! $trade) {
            return response()->json(['message' => __('p2p.trade_not_found')], 404);
        }

        $reason = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ])['reason'] ?? null;

        try {
            $trade = $this->sellTrades->openDispute($trade, $request->merchant, $reason);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $trade,
            'message' => __('p2p.sell_trade_dispute_opened'),
        ]);
    }
}
