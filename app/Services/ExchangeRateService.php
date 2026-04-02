<?php

namespace App\Services;

use App\Contracts\ExchangeRateProvider;
use Illuminate\Support\Facades\Cache;

class ExchangeRateService
{
    private const CACHE_KEY = 'exchange_rates';

    private const CACHE_TTL = 1800; // 30 minutes

    public function __construct(private readonly ExchangeRateProvider $provider) {}

    /**
     * Get the exchange rate for a given fiat currency (per 1 USDC).
     *
     * @throws \InvalidArgumentException if the currency is not available.
     */
    public function getRate(string $currency): float
    {
        $rates    = $this->resolveRates();
        $currency = strtoupper($currency);

        if (! isset($rates[$currency])) {
            throw new \InvalidArgumentException(
                __('trade.error.unsupported_currency', ['currency' => $currency])
            );
        }

        return (float) $rates[$currency];
    }

    /**
     * Convert a USDC amount to fiat.
     */
    public function convert(float $amountUsdc, string $currency): float
    {
        return round($amountUsdc * $this->getRate($currency), 2);
    }

    /**
     * Convert a fiat amount to USDC.
     */
    public function convertToUsdc(float $amountFiat, string $currency): float
    {
        return round($amountFiat / $this->getRate($currency), 6);
    }

    /**
     * Return all available rates, loading from cache or provider as needed.
     */
    private function resolveRates(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn () => $this->provider->fetchRates()
        );
    }
}
