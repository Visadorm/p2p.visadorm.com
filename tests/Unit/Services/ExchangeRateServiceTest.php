<?php

namespace Tests\Unit\Services;

use App\Services\ExchangeRates\ArrayExchangeRateProvider;
use App\Services\ExchangeRateService;
use InvalidArgumentException;
use Tests\TestCase;

class ExchangeRateServiceTest extends TestCase
{
    private ExchangeRateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $provider = new ArrayExchangeRateProvider([
            'DOP' => 57.0,
            'EUR' => 0.92,
            'HTG' => 132.0,
            'COP' => 4100.0,
            'NGN' => 1600.0,
            'USD' => 1.0,
        ]);

        $this->service = new ExchangeRateService($provider);
    }

    public function test_get_rate_returns_correct_rate_for_dop(): void
    {
        $this->assertSame(57.0, $this->service->getRate('DOP'));
    }

    public function test_get_rate_returns_correct_rate_for_eur(): void
    {
        $this->assertSame(0.92, $this->service->getRate('EUR'));
    }

    public function test_get_rate_returns_correct_rate_for_htg(): void
    {
        $this->assertSame(132.0, $this->service->getRate('HTG'));
    }

    public function test_get_rate_returns_correct_rate_for_cop(): void
    {
        $this->assertSame(4100.0, $this->service->getRate('COP'));
    }

    public function test_get_rate_returns_correct_rate_for_ngn(): void
    {
        $this->assertSame(1600.0, $this->service->getRate('NGN'));
    }

    public function test_get_rate_returns_correct_rate_for_usd(): void
    {
        $this->assertSame(1.0, $this->service->getRate('USD'));
    }

    public function test_get_rate_is_case_insensitive(): void
    {
        $this->assertSame(57.0, $this->service->getRate('dop'));
        $this->assertSame(0.92, $this->service->getRate('eur'));
    }

    public function test_get_rate_throws_for_unknown_currency(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->getRate('GBP');
    }

    public function test_convert_calculates_usdc_to_fiat_correctly(): void
    {
        // 100 USDC * 57.0 DOP/USDC = 5700.00
        $this->assertSame(5700.0, $this->service->convert(100.0, 'DOP'));
    }

    public function test_convert_rounds_to_two_decimal_places(): void
    {
        // 1.5 USDC * 0.92 EUR/USDC = 1.38
        $this->assertSame(1.38, $this->service->convert(1.5, 'EUR'));
    }

    public function test_convert_with_usd_returns_same_amount(): void
    {
        $this->assertSame(250.0, $this->service->convert(250.0, 'USD'));
    }

    public function test_convert_to_usdc_calculates_fiat_to_usdc_correctly(): void
    {
        // 5700 DOP / 57.0 = 100.0 USDC
        $this->assertSame(100.0, $this->service->convertToUsdc(5700.0, 'DOP'));
    }

    public function test_convert_to_usdc_rounds_to_six_decimal_places(): void
    {
        // 100 EUR / 0.92 = 108.695652...
        $result = $this->service->convertToUsdc(100.0, 'EUR');
        $this->assertSame(108.695652, $result);
    }

    public function test_convert_to_usdc_with_usd_returns_same_amount(): void
    {
        $this->assertSame(500.0, $this->service->convertToUsdc(500.0, 'USD'));
    }

    public function test_convert_to_usdc_with_cop(): void
    {
        // 4100 COP / 4100.0 = 1.0 USDC
        $this->assertSame(1.0, $this->service->convertToUsdc(4100.0, 'COP'));
    }

    public function test_convert_with_zero_amount(): void
    {
        $this->assertSame(0.0, $this->service->convert(0.0, 'DOP'));
    }

    public function test_convert_to_usdc_with_zero_amount(): void
    {
        $this->assertSame(0.0, $this->service->convertToUsdc(0.0, 'DOP'));
    }
}
