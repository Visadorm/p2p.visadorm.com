<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Settings\GeneralSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureP2pTradingEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(GeneralSettings::class)->p2p_trading_enabled) {
            return response()->json([
                'message' => __('p2p.trading_paused'),
            ], 503);
        }

        return $next($request);
    }
}
