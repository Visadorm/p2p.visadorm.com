<?php

declare(strict_types=1);

namespace Tests\Feature;

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
use Mockery;
use Tests\TestCase;

class EscrowBalanceIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MerchantRankSeeder::class);
        $rank = MerchantRank::where('slug', 'new-member')->first();
        $this->merchant = Merchant::create([
            'wallet_address' => '0x' . str_repeat('a', 40),
            'username' => 'm1',
            'is_active' => true,
            'rank_id' => $rank->id,
            'member_since' => now(),
            'total_volume' => 1000,
        ]);
    }

    private function makeTrade(TradeType $type, TradeStatus $status, float $amount): Trade
    {
        return Trade::create([
            'trade_hash' => '0x' . Str::random(64),
            'merchant_id' => $this->merchant->id,
            'seller_wallet' => '0x' . str_repeat('b', 40),
            'buyer_wallet' => '0x' . str_repeat('a', 40),
            'amount_usdc' => $amount,
            'amount_fiat' => $amount,
            'currency_code' => 'USD',
            'exchange_rate' => 1,
            'fee_amount' => 0.2,
            'payment_method' => '1',
            'is_cash_trade' => false,
            'type' => $type,
            'status' => $status,
            'expires_at' => now()->addHour(),
        ]);
    }

    public function test_a10_sell_trades_do_not_lock_merchant_escrow(): void
    {
        // Mock blockchain to return $1000 total escrow
        $blockchain = Mockery::mock(BlockchainService::class);
        $blockchain->shouldReceive('getMerchantEscrowBalance')->andReturn('0x' . dechex(1000_000_000));
        $blockchain->shouldReceive('usdcToHuman')->andReturn('1000');
        $this->app->instance(BlockchainService::class, $blockchain);

        $escrow = app(EscrowService::class);

        // Reproduce bug scenario: sell-flow trade with $20 amount + dispute
        $this->makeTrade(TradeType::Sell, TradeStatus::Disputed, 20);

        // Available should still be $1000 — sell trades don't touch merchant escrow.
        $this->assertEquals(0.0, $escrow->getLockedInTrades($this->merchant));
        $this->assertEquals(1000.0, $escrow->getMerchantAvailableBalance($this->merchant));
    }

    public function test_a10_buy_trades_still_lock_merchant_escrow(): void
    {
        $blockchain = Mockery::mock(BlockchainService::class);
        $blockchain->shouldReceive('getMerchantEscrowBalance')->andReturn('0x' . dechex(1000_000_000));
        $blockchain->shouldReceive('usdcToHuman')->andReturn('1000');
        $this->app->instance(BlockchainService::class, $blockchain);

        $escrow = app(EscrowService::class);

        // Buy trade for $50 in escrow_locked → must lock merchant escrow
        $this->makeTrade(TradeType::Buy, TradeStatus::EscrowLocked, 50);

        $this->assertEquals(50.0, $escrow->getLockedInTrades($this->merchant));
        $this->assertEquals(950.0, $escrow->getMerchantAvailableBalance($this->merchant));
    }

    public function test_a10_resolved_buy_trade_does_not_lock(): void
    {
        $blockchain = Mockery::mock(BlockchainService::class);
        $blockchain->shouldReceive('getMerchantEscrowBalance')->andReturn('0x' . dechex(1000_000_000));
        $blockchain->shouldReceive('usdcToHuman')->andReturn('1000');
        $this->app->instance(BlockchainService::class, $blockchain);

        $escrow = app(EscrowService::class);

        $this->makeTrade(TradeType::Buy, TradeStatus::Completed, 50);
        $this->makeTrade(TradeType::Buy, TradeStatus::Resolved, 30);
        $this->makeTrade(TradeType::Buy, TradeStatus::Cancelled, 25);

        $this->assertEquals(0.0, $escrow->getLockedInTrades($this->merchant));
    }

    public function test_a10_reconcile_balance_detects_divergence(): void
    {
        $blockchain = Mockery::mock(BlockchainService::class);
        // Total escrow = 1000
        $blockchain->shouldReceive('getMerchantEscrowBalance')->andReturn('0x' . dechex(1000_000_000));
        // Chain available = 950 (something locked on-chain that DB doesn't know)
        $blockchain->shouldReceive('getAvailableBalance')->andReturn('0x' . dechex(950_000_000));
        $blockchain->shouldReceive('usdcToHuman')->andReturnUsing(fn ($d) => (string) ((int) $d / 1_000_000));
        $this->app->instance(BlockchainService::class, $blockchain);

        $escrow = app(EscrowService::class);

        // DB has no locked trades → DB available = 1000; chain = 950 → divergence 50
        $result = $escrow->reconcileBalance($this->merchant);

        $this->assertEquals(1000.0, $result['db']);
        $this->assertEquals(950.0, $result['chain']);
        $this->assertEquals(50.0, $result['divergence']);
        $this->assertFalse($result['ok']);
    }

    public function test_a10_reconcile_balance_passes_when_aligned(): void
    {
        $blockchain = Mockery::mock(BlockchainService::class);
        $blockchain->shouldReceive('getMerchantEscrowBalance')->andReturn('0x' . dechex(1000_000_000));
        $blockchain->shouldReceive('getAvailableBalance')->andReturn('0x' . dechex(1000_000_000));
        $blockchain->shouldReceive('usdcToHuman')->andReturnUsing(fn ($d) => (string) ((int) $d / 1_000_000));
        $this->app->instance(BlockchainService::class, $blockchain);

        $escrow = app(EscrowService::class);

        $result = $escrow->reconcileBalance($this->merchant);
        $this->assertTrue($result['ok']);
        $this->assertEquals(0.0, $result['divergence']);
    }
}
