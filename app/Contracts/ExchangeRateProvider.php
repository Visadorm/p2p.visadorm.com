<?php

namespace App\Contracts;

interface ExchangeRateProvider
{
    /**
     * Fetch all available exchange rates.
     *
     * Returns an associative array of uppercase currency codes to their
     * rate relative to 1 USDC. Example: ['DOP' => 57.2, 'EUR' => 0.91].
     *
     * @throws \RuntimeException if the provider cannot be reached or returns unexpected data.
     */
    public function fetchRates(): array;
}
