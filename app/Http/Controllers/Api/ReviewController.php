<?php

namespace App\Http\Controllers\Api;

use App\Events\ReviewSubmitted;
use App\Http\Controllers\Controller;
use App\Enums\TradeStatus;
use App\Models\Merchant;
use App\Models\Review;
use App\Models\Trade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Both buyer and merchant can leave a review after a completed trade.
     * Each party reviews the OTHER party (buyer reviews seller, seller reviews buyer).
     */
    public function store(Request $request, string $tradeHash): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $trade = Trade::where('trade_hash', $tradeHash)->with('merchant')->first();

        if (! $trade) {
            return response()->json([
                'message' => __('p2p.trade_not_found'),
            ], 404);
        }

        if ($trade->status !== TradeStatus::Completed) {
            return response()->json([
                'message' => __('p2p.review_trade_not_completed'),
            ], 422);
        }

        $userWallet = strtolower($request->user()->wallet_address);
        $isBuyer = strtolower($trade->buyer_wallet) === $userWallet;
        $isMerchant = strtolower($trade->merchant->wallet_address) === $userWallet;

        if (! $isBuyer && ! $isMerchant) {
            return response()->json([
                'message' => __('p2p.trade_not_authorized'),
            ], 403);
        }

        $reviewerRole = $isBuyer ? 'buyer' : 'seller';

        $existsForThisWallet = Review::where('trade_id', $trade->id)
            ->where('reviewer_wallet', $userWallet)
            ->exists();

        if ($existsForThisWallet) {
            return response()->json([
                'message' => __('p2p.review_already_exists'),
            ], 422);
        }

        $existsForThisRole = Review::where('trade_id', $trade->id)
            ->where('reviewer_role', $reviewerRole)
            ->exists();

        if ($existsForThisRole) {
            return response()->json([
                'message' => __('p2p.review_role_already_submitted'),
            ], 422);
        }

        // Determine who is being reviewed (the OTHER party's merchant record)
        if ($isBuyer) {
            // Buyer reviews the seller — merchant_id = seller's merchant
            $reviewedMerchantId = $trade->merchant_id;
        } else {
            // Seller reviews the buyer — find buyer's merchant record
            $buyerMerchant = Merchant::where('wallet_address', $trade->buyer_wallet)->first();
            if (! $buyerMerchant) {
                return response()->json([
                    'message' => __('p2p.review_buyer_not_merchant'),
                ], 422);
            }
            $reviewedMerchantId = $buyerMerchant->id;
        }

        try {
            $review = Review::create([
                'trade_id' => $trade->id,
                'merchant_id' => $reviewedMerchantId,
                'reviewer_wallet' => $userWallet,
                'reviewer_role' => $reviewerRole,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'created_at' => now(),
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return response()->json([
                'message' => __('p2p.review_already_exists'),
            ], 422);
        }

        ReviewSubmitted::dispatch($review);

        return response()->json([
            'data' => $review,
            'message' => __('p2p.review_created'),
        ], 201);
    }
}
