<?php

namespace App\Http\Controllers;

use App\Models\MerchantTradingLink;
use Illuminate\Http\JsonResponse;

class TradeController extends Controller
{
    /**
     * Display a public trading link page.
     */
    public function show(string $slug): JsonResponse
    {
        $tradingLink = MerchantTradingLink::where('slug', $slug)
            ->where('is_active', true)
            ->with(['merchant' => function ($query) {
                $query->where('is_active', true)
                    ->with(['rank', 'currencies', 'paymentMethods']);
            }])
            ->firstOrFail();

        $merchant = $tradingLink->merchant;

        return response()->json([
            'trading_link' => [
                'slug' => $tradingLink->slug,
                'type' => $tradingLink->type,
                'label' => $tradingLink->label,
            ],
            'merchant' => [
                'username' => $merchant->username,
                'avatar' => $merchant->avatar,
                'rank' => $merchant->rank?->name,
                'is_legendary' => $merchant->is_legendary,
                'is_online' => $merchant->is_online,
                'total_trades' => $merchant->total_trades,
                'completion_rate' => $merchant->completion_rate,
                'avg_response_minutes' => $merchant->avg_response_minutes,
                'buyer_verification' => $merchant->buyer_verification,
                'trade_timer_minutes' => $merchant->trade_timer_minutes,
                'trade_instructions' => $merchant->trade_instructions,
                'currencies' => $merchant->currencies,
                'payment_methods' => $merchant->paymentMethods,
            ],
        ]);
    }
}
