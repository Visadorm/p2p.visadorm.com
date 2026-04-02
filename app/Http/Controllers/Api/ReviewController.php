<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\TradeStatus;
use App\Models\Trade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Buyer leaves a review after a completed trade.
     */
    public function store(Request $request, string $tradeHash): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $trade = Trade::where('trade_hash', $tradeHash)->first();

        if (! $trade) {
            return response()->json([
                'message' => __('p2p.trade_not_found'),
            ], 404);
        }

        $buyerWallet = strtolower($request->user()->wallet_address);

        if (strtolower($trade->buyer_wallet) !== $buyerWallet) {
            return response()->json([
                'message' => __('p2p.review_not_buyer'),
            ], 403);
        }

        if ($trade->status !== TradeStatus::Completed) {
            return response()->json([
                'message' => __('p2p.review_trade_not_completed'),
            ], 422);
        }

        if ($trade->review()->exists()) {
            return response()->json([
                'message' => __('p2p.review_already_exists'),
            ], 422);
        }

        $review = $trade->review()->create([
            'merchant_id' => $trade->merchant_id,
            'reviewer_wallet' => $buyerWallet,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => $review,
            'message' => __('p2p.review_created'),
        ], 201);
    }
}
