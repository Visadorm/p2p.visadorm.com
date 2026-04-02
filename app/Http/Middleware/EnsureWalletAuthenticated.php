<?php

namespace App\Http\Middleware;

use App\Models\Merchant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWalletAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->currentAccessToken()) {
            return response()->json(['message' => __('p2p.unauthorized')], 401);
        }

        $merchant = Merchant::where('wallet_address', $user->wallet_address)->first();

        if (! $merchant || ! $merchant->is_active) {
            return response()->json(['message' => __('p2p.merchant_not_found')], 403);
        }

        $merchant->update([
            'is_online' => true,
            'last_seen_at' => now(),
        ]);

        $request->merge(['merchant' => $merchant]);

        return $next($request);
    }
}
