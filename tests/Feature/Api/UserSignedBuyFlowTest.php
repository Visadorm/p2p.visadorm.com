<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\TradeStatus;
use App\Enums\TradeType;
use App\Models\Merchant;
use App\Models\MerchantRank;
use App\Models\Trade;
use App\Models\User;
use App\Services\BlockchainService;
use Database\Seeders\MerchantRankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class UserSignedBuyFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $merchantUser;
    private Merchant $merchant;
    private User $buyerUser;
    private Trade $trade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MerchantRankSeeder::class);
        $rank = MerchantRank::where('slug', 'new-member')->first();

        $this->merchantUser = User::create([
            'name' => 'Merchant', 'email' => 'm@t.com', 'password' => Hash::make('p'),
            'wallet_address' => '0x' . str_repeat('a', 40),
        ]);
        $this->merchant = Merchant::create([
            'wallet_address' => '0x' . str_repeat('a', 40), 'username' => 'm', 'is_active' => true,
            'rank_id' => $rank->id, 'member_since' => now(),
        ]);
        $this->buyerUser = User::create([
            'name' => 'Buyer', 'email' => 'b@t.com', 'password' => Hash::make('p'),
            'wallet_address' => '0x' . str_repeat('b', 40),
        ]);
        Merchant::create([
            'wallet_address' => '0x' . str_repeat('b', 40), 'username' => 'b', 'is_active' => true,
            'rank_id' => $rank->id, 'member_since' => now(),
        ]);

        $this->trade = Trade::create([
            'trade_hash' => '0x' . Str::random(64),
            'merchant_id' => $this->merchant->id,
            'buyer_wallet' => '0x' . str_repeat('b', 40),
            'amount_usdc' => 100, 'amount_fiat' => 100, 'currency_code' => 'USD',
            'exchange_rate' => 1, 'fee_amount' => 0.2, 'payment_method' => 'bank_transfer',
            'is_cash_trade' => false, 'type' => TradeType::Buy, 'status' => TradeStatus::EscrowLocked,
            'expires_at' => now()->addHour(),
        ]);

        // Mock BlockchainService calldata builders.
        $bc = Mockery::mock(BlockchainService::class);
        $bc->shouldReceive('markPaymentSentByBuyerCalldata')->andReturn('0xmarkpaid');
        $bc->shouldReceive('confirmPaymentByMerchantCalldata')->andReturn('0xconfirm');
        $bc->shouldReceive('cancelTradeByMerchantCalldata')->andReturn('0xcancel');
        $this->app->instance(BlockchainService::class, $bc);
    }

    public function test_b1_mark_paid_payload_returned_when_no_tx_hash(): void
    {
        Sanctum::actingAs($this->buyerUser);
        $res = $this->postJson("/api/trade/{$this->trade->trade_hash}/user-signed/mark-paid", []);
        $res->assertOk()->assertJsonPath('data.calldata', '0xmarkpaid');
    }

    public function test_b1_mark_paid_records_when_tx_hash_present(): void
    {
        Sanctum::actingAs($this->buyerUser);
        $tx = '0x' . str_repeat('1', 64);
        $this->postJson("/api/trade/{$this->trade->trade_hash}/user-signed/mark-paid", ['tx_hash' => $tx])
            ->assertOk();
        $fresh = $this->trade->fresh();
        $this->assertEquals(TradeStatus::PaymentSent, $fresh->status);
        $this->assertEquals($tx, $fresh->mark_paid_tx_hash);
    }

    public function test_b1_mark_paid_rejects_non_buyer(): void
    {
        Sanctum::actingAs($this->merchantUser);
        $this->postJson("/api/trade/{$this->trade->trade_hash}/user-signed/mark-paid", [])
            ->assertStatus(403);
    }

    public function test_b1_confirm_payload_returned_when_payment_sent(): void
    {
        $this->trade->update(['status' => TradeStatus::PaymentSent]);
        Sanctum::actingAs($this->merchantUser);
        $this->postJson("/api/trade/{$this->trade->trade_hash}/user-signed/confirm", [])
            ->assertOk()
            ->assertJsonPath('data.calldata', '0xconfirm');
    }

    public function test_b1_confirm_records_completion(): void
    {
        Event::fake();
        $this->trade->update(['status' => TradeStatus::PaymentSent]);
        Sanctum::actingAs($this->merchantUser);
        $tx = '0x' . str_repeat('2', 64);
        $this->postJson("/api/trade/{$this->trade->trade_hash}/user-signed/confirm", ['tx_hash' => $tx])
            ->assertOk();
        $fresh = $this->trade->fresh();
        $this->assertEquals(TradeStatus::Completed, $fresh->status);
        $this->assertEquals($tx, $fresh->release_tx_hash);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_b1_cancel_records_cancellation(): void
    {
        Sanctum::actingAs($this->merchantUser);
        $tx = '0x' . str_repeat('3', 64);
        $this->postJson("/api/trade/{$this->trade->trade_hash}/user-signed/cancel", ['tx_hash' => $tx])
            ->assertOk();
        $fresh = $this->trade->fresh();
        $this->assertEquals(TradeStatus::Cancelled, $fresh->status);
        $this->assertEquals($tx, $fresh->cancel_tx_hash);
    }

    public function test_b1_confirm_rejects_non_merchant(): void
    {
        $this->trade->update(['status' => TradeStatus::PaymentSent]);
        Sanctum::actingAs($this->buyerUser);
        $this->postJson("/api/trade/{$this->trade->trade_hash}/user-signed/confirm", [])
            ->assertStatus(403);
    }
}
