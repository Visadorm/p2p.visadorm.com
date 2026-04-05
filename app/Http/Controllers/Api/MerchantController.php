<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\BuyerVerification;
use App\Enums\DisputeStatus;
use App\Enums\TradeStatus;
use App\Models\Merchant;
use App\Services\EscrowService;
use App\Services\ExchangeRateService;
use App\Settings\BlockchainSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MerchantController extends Controller
{
    /**
     * Merchant dashboard data (stats, active trades, escrow balance).
     */
    public function show(Request $request): JsonResponse
    {
        $merchant = $request->merchant;

        $activeTrades = $merchant->allTrades()
            ->whereIn('status', [
                TradeStatus::Pending,
                TradeStatus::EscrowLocked,
                TradeStatus::PaymentSent,
            ])
            ->count();

        $openDisputes = $merchant->allTrades()
            ->where('status', TradeStatus::Disputed)
            ->count();

        $escrowService = app(\App\Services\EscrowService::class);
        $escrowBalance = $escrowService->getMerchantTotalEscrow($merchant);
        $lockedBalance = $escrowService->getLockedInTrades($merchant);

        return response()->json([
            'data' => [
                'merchant' => $merchant->load('rank'),
                'stats' => [
                    'total_trades' => $merchant->total_trades,
                    'total_volume' => $merchant->total_volume,
                    'completion_rate' => $merchant->completion_rate,
                    'reliability_score' => $merchant->reliability_score,
                    'dispute_rate' => $merchant->dispute_rate,
                    'avg_response_minutes' => $merchant->avg_response_minutes,
                ],
                'active_trades_count' => $activeTrades,
                'open_disputes_count' => $openDisputes,
                'escrow_balance' => $escrowBalance,
                'locked_balance' => $lockedBalance,
                'sms_enabled' => app(\App\Settings\NotificationSettings::class)->sms_notifications_enabled,
            ],
            'message' => __('p2p.dashboard_loaded'),
        ]);
    }

    /**
     * Update merchant profile settings.
     */
    public function update(Request $request): JsonResponse
    {
        $merchant = $request->merchant;

        $validated = $request->validate([
            'username' => ['sometimes', 'string', 'max:50', Rule::unique('merchants')->ignore($merchant->id)],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500'],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'trade_instructions' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'trade_timer_minutes' => ['sometimes', 'integer', 'min:10', 'max:120'],
            'buyer_verification' => ['sometimes', Rule::enum(BuyerVerification::class)],
            'notify_bank_proof' => ['sometimes', 'boolean'],
            'notify_buyer_id' => ['sometimes', 'boolean'],
            'notify_email' => ['sometimes', 'boolean'],
            'notify_sms' => ['sometimes', 'boolean'],
        ]);

        $merchant->update($validated);

        // Auto-create public trading link when username is first set
        if (isset($validated['username']) && $merchant->tradingLinks()->count() === 0) {
            $merchant->tradingLinks()->create([
                'slug' => $validated['username'],
                'type' => 'public',
                'label' => 'Buy USDC',
                'is_primary' => true,
                'is_active' => true,
            ]);
        }

        return response()->json([
            'data' => $merchant->fresh()->load('rank'),
            'message' => __('p2p.merchant_updated'),
        ]);
    }

    /**
     * Upload or replace the merchant's profile avatar.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $merchant = $request->merchant;

        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'], // 2MB max
        ]);

        // Delete old avatar if stored locally
        if ($merchant->avatar && str_starts_with($merchant->avatar, '/storage/')) {
            $oldPath = 'avatars/' . $merchant->id . '/' . basename($merchant->avatar);
            Storage::disk('public')->delete($oldPath);
        }

        $file = $request->file('avatar');
        $path = $file->store('avatars/' . $merchant->id, 'public');

        // Store relative path — works with any domain/proxy
        $url = '/storage/' . $path;

        $merchant->update(['avatar' => $url]);

        return response()->json([
            'data' => ['avatar' => $url],
            'message' => __('p2p.avatar_updated'),
        ]);
    }

    /**
     * Public merchant profile page.
     */
    public function profile(string $username, ExchangeRateService $rates): JsonResponse
    {
        $merchant = Merchant::where('username', $username)
            ->where('is_active', true)
            ->with([
                'rank',
                'currencies' => fn ($q) => $q->where('is_active', true),
                'paymentMethods' => fn ($q) => $q->where('is_active', true),
                'tradingLinks' => fn ($q) => $q->where('is_active', true),
            ])
            ->first();

        if (! $merchant) {
            return response()->json([
                'message' => __('p2p.merchant_not_active'),
            ], 404);
        }

        // Recalculate badges on every profile load (ensures liquidity badge is current)
        rescue(fn () => app(\App\Services\MerchantBadgeService::class)->updateBadges($merchant));
        $merchant->refresh();

        $reviews = $merchant->reviews()
            ->where('is_hidden', false)
            ->latest('created_at')
            ->limit(10)
            ->get(['id', 'trade_id', 'reviewer_wallet', 'reviewer_role', 'rating', 'comment', 'created_at']);

        // Single query for count + avg instead of 2 separate queries
        $reviewAgg = $merchant->reviews()
            ->where('is_hidden', false)
            ->selectRaw('COUNT(*) as review_count, AVG(rating) as avg_rating')
            ->first();

        $avgRating = (float) $reviewAgg->avg_rating;
        $reviewCount = (int) $reviewAgg->review_count;

        $activeTrades = $merchant->allTrades()
            ->whereIn('status', [TradeStatus::Pending, TradeStatus::EscrowLocked, TradeStatus::PaymentSent])
            ->count();

        $escrowService   = app(EscrowService::class);
        $escrowBalance   = $escrowService->getMerchantTotalEscrow($merchant);
        $lockedBalance   = $escrowService->getLockedInTrades($merchant);
        $escrowAddress   = rescue(fn () => app(BlockchainSettings::class)->trade_escrow_address, '');

        $recentTrades = $merchant->allTrades()
            ->where('status', TradeStatus::Completed)
            ->orderByDesc('completed_at')
            ->limit(10)
            ->get(['merchant_id', 'buyer_wallet', 'amount_usdc', 'currency_code', 'payment_method', 'meeting_location', 'completed_at']);

        return response()->json([
            'data' => [
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
                    'avg_response_minutes' => $merchant->avg_response_minutes,
                    'total_trades' => $merchant->total_trades,
                    'total_volume' => $merchant->total_volume,
                    'completion_rate' => $merchant->completion_rate,
                    'reliability_score' => $merchant->reliability_score,
                    'member_since' => $merchant->member_since?->format('F Y'),
                    'trade_instructions' => $merchant->trade_instructions,
                    'trade_timer_minutes' => $merchant->trade_timer_minutes,
                    'buyer_verification' => $merchant->buyer_verification?->value,
                    'currencies' => $merchant->currencies->map(fn ($c) => [
                        'id' => $c->id,
                        'currency_code' => $c->currency_code,
                        'markup_percent' => $c->markup_percent,
                        'market_rate' => rescue(fn () => $rates->getRate($c->currency_code), 0),
                        'min_amount' => $c->min_amount,
                        'max_amount' => $c->max_amount,
                    ]),
                    'payment_methods' => $merchant->paymentMethods->map(fn ($pm) => [
                        'id' => $pm->id,
                        'type' => $pm->type?->value,
                        'provider' => $pm->provider,
                        'label' => $pm->label,
                        'location' => $pm->location,
                    ]),
                    'trading_links' => $merchant->tradingLinks->map(fn ($tl) => [
                        'id' => $tl->id,
                        'slug' => $tl->slug,
                        'type' => $tl->type?->value,
                        'is_primary' => $tl->is_primary,
                        'label' => $tl->label,
                    ]),
                    'escrow_balance' => $escrowBalance,
                    'locked_balance' => $lockedBalance,
                    'escrow_address' => $escrowAddress,
                    'active_trades' => $activeTrades,
                    'avg_rating' => round($avgRating ?? 0, 1),
                    'review_count' => $reviewCount,
                    'reviews' => $reviews,
                    'recent_trades' => $recentTrades->map(fn ($t) => [
                        'counterparty' => $t->merchant_id === $merchant->id
                            ? substr($t->buyer_wallet, 0, 4) . '***' . substr($t->buyer_wallet, -2)
                            : 'Seller',
                        'role' => $t->merchant_id === $merchant->id ? 'sell' : 'buy',
                        'amount' => $t->amount_usdc,
                        'currency_code' => $t->currency_code,
                        'payment_method' => $t->payment_method,
                        'meeting_location' => $t->meeting_location,
                        'time' => $t->completed_at?->diffForHumans(),
                    ]),
                ],
            ],
            'message' => __('p2p.merchant_profile_loaded'),
        ]);
    }
}
