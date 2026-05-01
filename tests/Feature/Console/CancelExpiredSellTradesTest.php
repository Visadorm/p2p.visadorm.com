<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\TradeStatus;
use App\Enums\TradeType;
use App\Models\Merchant;
use App\Models\MerchantRank;
use App\Models\Trade;
use App\Services\BlockchainService;
use App\Settings\TradeSettings;
use Database\Seeders\MerchantRankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class CancelExpiredSellTradesTest extends TestCase
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
        ]);
    }

    private function makeTrade(TradeStatus $status, \DateTimeInterface $expiresAt, ?string $fundTxHash = '0xfund'): Trade
    {
        return Trade::create([
            'trade_hash' => '0x' . Str::random(64),
            'merchant_id' => $this->merchant->id,
            'seller_wallet' => '0x' . str_repeat('b', 40),
            'buyer_wallet' => '0x' . str_repeat('a', 40),
            'amount_usdc' => 100,
            'amount_fiat' => 100,
            'currency_code' => 'USD',
            'exchange_rate' => 1,
            'fee_amount' => 0.2,
            'payment_method' => '1',
            'is_cash_trade' => false,
            'type' => TradeType::Sell,
            'status' => $status,
            'fund_tx_hash' => $fundTxHash,
            'expires_at' => $expiresAt,
        ]);
    }

    public function test_a09_cancels_expired_pending_and_escrow_locked_trades(): void
    {
        $blockchain = Mockery::mock(BlockchainService::class);
        $blockchain->shouldReceive('cancelExpiredSellTrade')
            ->twice()
            ->andReturn('0x' . str_repeat('c', 64), '0x' . str_repeat('d', 64));
        $this->app->instance(BlockchainService::class, $blockchain);

        $expiredPending = $this->makeTrade(TradeStatus::Pending, now()->subMinute());
        $expiredEscrow = $this->makeTrade(TradeStatus::EscrowLocked, now()->subMinute());
        $stillActive = $this->makeTrade(TradeStatus::EscrowLocked, now()->addHour());

        $this->artisan('p2p:cancel-expired-sell-trades')->assertSuccessful();

        $this->assertEquals(TradeStatus::Cancelled, $expiredPending->fresh()->status);
        $this->assertEquals(TradeStatus::Cancelled, $expiredEscrow->fresh()->status);
        $this->assertNotNull($expiredPending->fresh()->cancel_tx_hash);
        $this->assertNotNull($expiredEscrow->fresh()->cancel_tx_hash);

        // Active trade untouched
        $this->assertEquals(TradeStatus::EscrowLocked, $stillActive->fresh()->status);
    }

    public function test_a09_skips_payment_sent_trades(): void
    {
        // After PaymentSent, timer must STOP — never auto-cancel.
        $blockchain = Mockery::mock(BlockchainService::class);
        $blockchain->shouldNotReceive('cancelExpiredSellTrade');
        $this->app->instance(BlockchainService::class, $blockchain);

        $expiredPaid = $this->makeTrade(TradeStatus::PaymentSent, now()->subHour());

        $this->artisan('p2p:cancel-expired-sell-trades')->assertSuccessful();

        $this->assertEquals(TradeStatus::PaymentSent, $expiredPaid->fresh()->status);
    }

    public function test_a09_skips_disputed_completed_cancelled(): void
    {
        $blockchain = Mockery::mock(BlockchainService::class);
        $blockchain->shouldNotReceive('cancelExpiredSellTrade');
        $this->app->instance(BlockchainService::class, $blockchain);

        $this->makeTrade(TradeStatus::Disputed, now()->subHour());
        $this->makeTrade(TradeStatus::Completed, now()->subHour());
        $this->makeTrade(TradeStatus::Cancelled, now()->subHour());

        $this->artisan('p2p:cancel-expired-sell-trades')->assertSuccessful();
    }

    public function test_a09_skips_unfunded_trades(): void
    {
        // Pending row without fund_tx_hash means no on-chain escrow exists.
        // Calling cancelExpiredSellTrade would revert. Filter excludes them.
        $blockchain = Mockery::mock(BlockchainService::class);
        $blockchain->shouldNotReceive('cancelExpiredSellTrade');
        $this->app->instance(BlockchainService::class, $blockchain);

        $this->makeTrade(TradeStatus::Pending, now()->subHour(), null);

        $this->artisan('p2p:cancel-expired-sell-trades')->assertSuccessful();
    }

    public function test_a09_disabled_setting_short_circuits(): void
    {
        $settings = app(TradeSettings::class);
        $settings->sell_auto_cancel_expired_enabled = false;
        $settings->save();

        $blockchain = Mockery::mock(BlockchainService::class);
        $blockchain->shouldNotReceive('cancelExpiredSellTrade');
        $this->app->instance(BlockchainService::class, $blockchain);

        $this->makeTrade(TradeStatus::Pending, now()->subHour());

        $this->artisan('p2p:cancel-expired-sell-trades')
            ->expectsOutput('Auto-cancel disabled in settings — skipping.')
            ->assertSuccessful();

        // restore
        $settings->sell_auto_cancel_expired_enabled = true;
        $settings->save();
    }
}
