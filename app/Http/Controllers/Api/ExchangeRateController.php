<?php

namespace App\Http\Controllers\Api;

use App\Contracts\ExchangeRateProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ExchangeRateController extends Controller
{
    /**
     * Return all available exchange rates (1 USDC → fiat).
     */
    public function index(ExchangeRateProvider $provider): JsonResponse
    {
        $rates = Cache::remember('exchange_rates', 1800, fn () => $provider->fetchRates());

        return response()->json([
            'data' => $rates,
            'message' => __('p2p.exchange_rates_loaded'),
        ]);
    }
}
