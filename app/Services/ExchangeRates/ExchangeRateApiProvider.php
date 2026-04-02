<?php

namespace App\Services\ExchangeRates;

use App\Contracts\ExchangeRateProvider;
use Illuminate\Support\Facades\Http;

/**
 * Exchange rate provider using ExchangeRate-API (v6).
 *
 * Returns rates for 1 USD (≈ 1 USDC) in all supported fiat currencies.
 * Set EXCHANGERATE_API_KEY in .env to authenticate.
 *
 * To swap: create a new class implementing ExchangeRateProvider and
 * update the binding in AppServiceProvider.
 */
class ExchangeRateApiProvider implements ExchangeRateProvider
{
    public function fetchRates(): array
    {
        $apiKey = config('services.exchangerate.api_key');

        if (empty($apiKey)) {
            throw new \RuntimeException(
                'EXCHANGERATE_API_KEY is not configured'
            );
        }

        $response = Http::timeout(8)
            ->acceptJson()
            ->get("https://v6.exchangerate-api.com/v6/{$apiKey}/latest/USD");

        if (! $response->ok()) {
            throw new \RuntimeException(
                "ExchangeRate API request failed with status {$response->status()}"
            );
        }

        $data = $response->json();

        if (($data['result'] ?? '') !== 'success' || ! isset($data['conversion_rates'])) {
            throw new \RuntimeException(
                'ExchangeRate API returned an unexpected response'
            );
        }

        // Keys are already uppercase (USD, EUR, DOP, NGN, etc.)
        return array_map('floatval', $data['conversion_rates']);
    }
}
