<?php

namespace App\Services\ExchangeRates;

use App\Contracts\ExchangeRateProvider;

/**
 * In-memory provider backed by a fixed array.
 * Use in tests and local development to avoid hitting the real API.
 */
class ArrayExchangeRateProvider implements ExchangeRateProvider
{
    public function __construct(private readonly array $rates) {}

    public function fetchRates(): array
    {
        return $this->rates;
    }
}
