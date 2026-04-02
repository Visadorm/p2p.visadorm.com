<?php

namespace App\Http\Controllers;

use App\Enums\TradeStatus;
use App\Models\Merchant;
use App\Models\Review;
use Illuminate\Http\JsonResponse;

class MerchantController extends Controller
{
    public function profile(string $username): JsonResponse
    {
        $merchant = Merchant::where('username', $username)
            ->where('is_active', true)
            ->with([
                'rank',
                'currencies' => fn ($q) => $q->where('is_active', true),
                'paymentMethods' => fn ($q) => $q->where('is_active', true),
                'tradingLinks' => fn ($q) => $q->where('is_active', true),
            ])
            ->firstOrFail();

        $reviews = Review::where('merchant_id', $merchant->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'reviewer_wallet', 'rating', 'comment', 'created_at']);

        $avgRating = Review::where('merchant_id', $merchant->id)->avg('rating');
        $reviewCount = Review::where('merchant_id', $merchant->id)->count();

        $activeTrades = $merchant->trades()
            ->whereIn('status', [TradeStatus::Pending, TradeStatus::EscrowLocked, TradeStatus::PaymentSent])
            ->count();

        $escrowBalance = app(\App\Services\EscrowService::class)->getMerchantAvailableBalance($merchant);

        $recentTrades = $merchant->trades()
            ->where('status', TradeStatus::Completed)
            ->orderByDesc('completed_at')
            ->limit(10)
            ->get(['buyer_wallet', 'amount_usdc', 'completed_at']);

        return response()->json([
            'merchant' => [
                'username' => $merchant->username,
                'wallet_address' => $merchant->wallet_address,
                'bio' => $merchant->bio,
                'avatar' => $merchant->avatar,
                'rank' => $merchant->rank?->name,
                'is_legendary' => $merchant->is_legendary,
                'kyc_status' => $merchant->kyc_status?->value,
                'bank_verified' => $merchant->bank_verified,
                'email_verified' => $merchant->email_verified,
                'business_verified' => $merchant->business_verified,
                'is_fast_responder' => $merchant->is_fast_responder,
                'has_liquidity' => $merchant->has_liquidity,
                'is_online' => $merchant->is_online,
                'last_seen_at' => $merchant->last_seen_at,
                'avg_response_minutes' => $merchant->avg_response_minutes,
                'total_trades' => $merchant->total_trades,
                'total_volume' => $merchant->total_volume,
                'completion_rate' => $merchant->completion_rate,
                'reliability_score' => $merchant->reliability_score,
                'dispute_rate' => $merchant->dispute_rate,
                'member_since' => $merchant->member_since,
                'trade_instructions' => $merchant->trade_instructions,
                'trade_timer_minutes' => $merchant->trade_timer_minutes,
                'buyer_verification' => $merchant->buyer_verification?->value,
                'currencies' => $merchant->currencies,
                'payment_methods' => $merchant->paymentMethods,
                'trading_links' => $merchant->tradingLinks,
                'escrow_balance' => $escrowBalance,
                'active_trades' => $activeTrades,
                'reviews' => $reviews,
                'avg_rating' => round($avgRating ?? 0, 1),
                'review_count' => $reviewCount,
                'recent_trades' => $recentTrades->map(fn ($t) => [
                    'buyer' => substr($t->buyer_wallet, 0, 4) . '***' . substr($t->buyer_wallet, -2),
                    'amount' => $t->amount_usdc,
                    'time' => $t->completed_at?->diffForHumans(),
                ]),
            ],
        ]);
    }
}
