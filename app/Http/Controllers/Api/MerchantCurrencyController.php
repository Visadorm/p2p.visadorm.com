<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MerchantCurrency;
use App\Services\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantCurrencyController extends Controller
{
    /**
     * List merchant's currencies with live market rates attached.
     */
    public function index(Request $request, ExchangeRateService $rates): JsonResponse
    {
        $merchant = $request->merchant;

        $currencies = $merchant->currencies()->latest()->get()->map(function (MerchantCurrency $currency) use ($rates) {
            $data = $currency->toArray();

            try {
                $data['market_rate'] = $rates->getRate($currency->currency_code);
            } catch (\InvalidArgumentException) {
                $data['market_rate'] = 0;
            }

            return $data;
        });

        return response()->json([
            'data' => $currencies,
            'message' => __('p2p.currencies_loaded'),
        ]);
    }

    /**
     * Add a new currency for the merchant.
     */
    public function store(Request $request): JsonResponse
    {
        $merchant = $request->merchant;

        $validated = $request->validate([
            'currency_code' => ['required', 'string', 'max:3'],
            'markup_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['required', 'numeric', 'gt:min_amount'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $currency = $merchant->currencies()->create($validated);

        return response()->json([
            'data' => $currency,
            'message' => __('p2p.currency_created'),
        ], 201);
    }

    /**
     * Update a merchant currency.
     */
    public function update(Request $request, MerchantCurrency $currency): JsonResponse
    {
        $merchant = $request->merchant;

        if ($currency->merchant_id !== $merchant->id) {
            return response()->json([
                'message' => __('p2p.forbidden'),
            ], 403);
        }

        $validated = $request->validate([
            'currency_code' => ['sometimes', 'string', 'max:3'],
            'markup_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'min_amount' => ['sometimes', 'numeric', 'min:0'],
            'max_amount' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $currency->update($validated);

        return response()->json([
            'data' => $currency->fresh(),
            'message' => __('p2p.currency_updated'),
        ]);
    }

    /**
     * Delete a merchant currency.
     */
    public function destroy(Request $request, MerchantCurrency $currency): JsonResponse
    {
        $merchant = $request->merchant;

        if ($currency->merchant_id !== $merchant->id) {
            return response()->json([
                'message' => __('p2p.forbidden'),
            ], 403);
        }

        $currency->delete();

        return response()->json([
            'message' => __('p2p.currency_deleted'),
        ]);
    }
}
