<?php

namespace Tests\Unit\Services;

use App\Enums\TradeStatus;
use App\Enums\TradeType;
use App\Models\Merchant;
use App\Models\MerchantRank;
use App\Models\Trade;
use App\Services\BlockchainService;
use App\Services\EscrowService;
use Database\Seeders\MerchantRankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EscrowServiceTest extends TestCase
{
    use RefreshDatabase;

    private EscrowService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MerchantRankSeeder::class);

        // Mock BlockchainService to throw so EscrowService uses database fallback
        $mockBlockchain = $this->createMock(BlockchainService::class);
        $mockBlockchain->method('getAvailableBalance')
            ->willThrowException(new \RuntimeException('No contract deployed'));
        $mockBlockchain->method('getMerchantEscrowBalance')
            ->willThrowException(new \RuntimeException('No contract deployed'));

        $this->service = new EscrowService($mockBlockchain);
    }

    private function createMerchant(array $overrides = []): Merchant
    {
        $rank = MerchantRank::where('slug', 'new-member')->first();

        return Merchant::create(array_merge([
            'wallet_address' => '0x' . fake()->sha1(),
            'username' => 'user_' . fake()->unique()->word(),
            'is_active' => true,
            'total_trades' => 0,
            'completion_rate' => 0,
            'total_volume' => 10000,
            'rank_id' => $rank->id,
            'member_since' => now(),
        ], $overrides));
    }

    private function createTrade(Merchant $merchant, TradeStatus $status, float $amountUsdc = 100.0): Trade
    {
        return Trade::create([
            'trade_hash' => '0x' . Str::random(64),
            'merchant_id' => $merchant->id,
            'buyer_wallet' => '0x' . fake()->sha1(),
            'amount_usdc' => $amountUsdc,
            'amount_fiat' => $amountUsdc * 57.0,
            'currency_code' => 'DOP',
            'exchange_rate' => 57.0,
            'fee_amount' => round($amountUsdc * 0.002, 6),
            'payment_method' => 'bank_transfer',
            'type' => TradeType::Buy,
            'status' => $status,
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    public function test_get_merchant_available_balance_with_no_trades(): void
    {
        $merchant = $this->createMerchant(['total_volume' => 5000]);

        $balance = $this->service->getMerchantAvailableBalance($merchant);

        $this->assertSame(5000.0, $balance);
    }

    public function test_get_merchant_available_balance_subtracts_locked_trades(): void
    {
        $merchant = $this->createMerchant(['total_volume' => 5000]);

        $this->createTrade($merchant, TradeStatus::Pending, 500);
        $this->createTrade($merchant, TradeStatus::EscrowLocked, 300);

        $balance = $this->service->getMerchantAvailableBalance($merchant);

        $this->assertSame(4200.0, $balance);
    }

    public function test_get_locked_in_trades_counts_only_active_statuses(): void
    {
        $merchant = $this->createMerchant();

        // Active statuses: Pending, EscrowLocked, PaymentSent
        $this->createTrade($merchant, TradeStatus::Pending, 100);
        $this->createTrade($merchant, TradeStatus::EscrowLocked, 200);
        $this->createTrade($merchant, TradeStatus::PaymentSent, 300);

        // Non-active statuses should be excluded
        $this->createTrade($merchant, TradeStatus::Completed, 500);
        $this->createTrade($merchant, TradeStatus::Cancelled, 400);
        $this->createTrade($merchant, TradeStatus::Expired, 600);
        // Disputed trades are also active — funds remain locked on-chain during disputes
        $this->createTrade($merchant, TradeStatus::Disputed, 700);

        $locked = $this->service->getLockedInTrades($merchant);

        // Pending(100) + EscrowLocked(200) + PaymentSent(300) + Disputed(700)
        $this->assertSame(1300.0, $locked);
    }

    public function test_get_locked_in_trades_returns_zero_with_no_active_trades(): void
    {
        $merchant = $this->createMerchant();

        $this->createTrade($merchant, TradeStatus::Completed, 500);
        $this->createTrade($merchant, TradeStatus::Cancelled, 300);

        $locked = $this->service->getLockedInTrades($merchant);

        $this->assertSame(0.0, $locked);
    }

    public function test_get_locked_in_trades_does_not_count_other_merchants(): void
    {
        $merchant = $this->createMerchant();
        $otherMerchant = $this->createMerchant();

        $this->createTrade($merchant, TradeStatus::Pending, 100);
        $this->createTrade($otherMerchant, TradeStatus::Pending, 500);

        $locked = $this->service->getLockedInTrades($merchant);

        $this->assertSame(100.0, $locked);
    }

    public function test_can_initiate_trade_returns_true_when_balance_sufficient(): void
    {
        $merchant = $this->createMerchant(['total_volume' => 5000]);

        $this->assertTrue($this->service->canInitiateTrade($merchant, 5000));
    }

    public function test_can_initiate_trade_returns_true_when_balance_exactly_matches(): void
    {
        $merchant = $this->createMerchant(['total_volume' => 1000]);

        $this->createTrade($merchant, TradeStatus::Pending, 500);

        // Available = 1000 - 500 = 500
        $this->assertTrue($this->service->canInitiateTrade($merchant, 500));
    }

    public function test_can_initiate_trade_returns_false_when_balance_insufficient(): void
    {
        $merchant = $this->createMerchant(['total_volume' => 1000]);

        $this->createTrade($merchant, TradeStatus::Pending, 800);

        // Available = 1000 - 800 = 200
        $this->assertFalse($this->service->canInitiateTrade($merchant, 300));
    }

    public function test_can_initiate_trade_returns_false_when_fully_locked(): void
    {
        $merchant = $this->createMerchant(['total_volume' => 1000]);

        $this->createTrade($merchant, TradeStatus::Pending, 1000);

        // Available = 1000 - 1000 = 0
        $this->assertFalse($this->service->canInitiateTrade($merchant, 1));
    }

    public function test_available_balance_with_zero_volume(): void
    {
        $merchant = $this->createMerchant(['total_volume' => 0]);

        $balance = $this->service->getMerchantAvailableBalance($merchant);

        $this->assertSame(0.0, $balance);
    }
}
