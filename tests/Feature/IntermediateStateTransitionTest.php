<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TradeStatus;
use App\Enums\TradeType;
use App\Models\Merchant;
use App\Models\MerchantRank;
use App\Models\Trade;
use App\Services\TradeService;
use Database\Seeders\MerchantRankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class IntermediateStateTransitionTest extends TestCase
{
    use RefreshDatabase;

    private Trade $trade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MerchantRankSeeder::class);
        $rank = MerchantRank::where('slug', 'new-member')->first();
        $merchant = Merchant::create([
            'wallet_address' => '0x' . str_repeat('a', 40),
            'username' => 'm',
            'is_active' => true,
            'rank_id' => $rank->id,
            'member_since' => now(),
        ]);

        $this->trade = Trade::create([
            'trade_hash' => '0x' . Str::random(64),
            'merchant_id' => $merchant->id,
            'buyer_wallet' => '0x' . str_repeat('b', 40),
            'amount_usdc' => 100,
            'amount_fiat' => 100,
            'currency_code' => 'USD',
            'exchange_rate' => 1,
            'fee_amount' => 0.2,
            'payment_method' => 'bank_transfer',
            'is_cash_trade' => false,
            'type' => TradeType::Buy,
            'status' => TradeStatus::PaymentSent,
            'expires_at' => now()->addHour(),
            'escrow_tx_hash' => '0x' . str_repeat('1', 64),
        ]);
    }

    public function test_b3_confirm_payment_sets_intermediate_state_only(): void
    {
        Event::fake();
        app(TradeService::class)->confirmPayment($this->trade);
        $this->trade->refresh();

        $this->assertSame(TradeStatus::Confirming, $this->trade->status);
        $this->assertNull($this->trade->completed_at);
        $this->assertNull($this->trade->release_tx_hash);

        Event::assertNotDispatched(\App\Events\TradeCompleted::class);
    }

    public function test_b3_finalize_confirmed_payment_marks_complete(): void
    {
        Event::fake();
        $this->trade->update(['status' => TradeStatus::Confirming]);

        $tx = '0x' . str_repeat('c', 64);
        app(TradeService::class)->finalizeConfirmedPayment($this->trade, $tx);
        $this->trade->refresh();

        $this->assertSame(TradeStatus::Completed, $this->trade->status);
        $this->assertSame($tx, $this->trade->release_tx_hash);
        $this->assertNotNull($this->trade->completed_at);

        Event::assertDispatched(\App\Events\TradeCompleted::class);
    }

    public function test_b4_cancel_sets_intermediate_state_only(): void
    {
        Event::fake();
        $this->trade->update(['status' => TradeStatus::EscrowLocked]);

        app(TradeService::class)->cancelTrade($this->trade);
        $this->trade->refresh();

        $this->assertSame(TradeStatus::Cancelling, $this->trade->status);
        $this->assertNull($this->trade->cancel_tx_hash);

        Event::assertNotDispatched(\App\Events\TradeCancelled::class);
    }

    public function test_b4_finalize_cancelled_trade_marks_cancelled(): void
    {
        Event::fake();
        $this->trade->update(['status' => TradeStatus::Cancelling]);

        $tx = '0x' . str_repeat('d', 64);
        app(TradeService::class)->finalizeCancelledTrade($this->trade, $tx);
        $this->trade->refresh();

        $this->assertSame(TradeStatus::Cancelled, $this->trade->status);
        $this->assertSame($tx, $this->trade->cancel_tx_hash);

        Event::assertDispatched(\App\Events\TradeCancelled::class);
    }

    public function test_b3_finalize_idempotent_on_same_tx_hash(): void
    {
        Event::fake();
        $tx = '0x' . str_repeat('c', 64);
        $this->trade->update(['status' => TradeStatus::Completed, 'release_tx_hash' => $tx]);

        app(TradeService::class)->finalizeConfirmedPayment($this->trade, $tx);

        Event::assertNotDispatched(\App\Events\TradeCompleted::class);
    }

    public function test_b4_finalize_idempotent_on_same_tx_hash(): void
    {
        Event::fake();
        $tx = '0x' . str_repeat('d', 64);
        $this->trade->update(['status' => TradeStatus::Cancelled, 'cancel_tx_hash' => $tx]);

        app(TradeService::class)->finalizeCancelledTrade($this->trade, $tx);

        Event::assertNotDispatched(\App\Events\TradeCancelled::class);
    }
}
