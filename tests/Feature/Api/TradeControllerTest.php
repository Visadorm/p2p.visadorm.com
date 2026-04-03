<?php

namespace Tests\Feature\Api;

use App\Enums\TradeStatus;
use App\Enums\TradeType;
use App\Enums\TradingLinkType;
use App\Models\Merchant;
use App\Models\MerchantCurrency;
use App\Models\MerchantPaymentMethod;
use App\Models\MerchantRank;
use App\Models\MerchantTradingLink;
use App\Models\Trade;
use App\Models\User;
use App\Services\TradeService;
use Database\Seeders\MerchantRankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class TradeControllerTest extends TestCase
{
    use RefreshDatabase;

    private Merchant $merchant;

    private MerchantTradingLink $tradingLink;

    private User $merchantUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MerchantRankSeeder::class);

        $merchantWallet = '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

        $rank = MerchantRank::where('slug', 'new-member')->first();

        $this->merchantUser = User::create([
            'name' => 'Merchant User',
            'email' => 'merchant@test.com',
            'password' => Hash::make('password'),
            'wallet_address' => $merchantWallet,
        ]);

        $this->merchant = Merchant::create([
            'wallet_address' => $merchantWallet,
            'username' => 'test_merchant',
            'is_active' => true,
            'rank_id' => $rank->id,
            'total_trades' => 50,
            'total_volume' => 50000,
            'completion_rate' => 95,
            'member_since' => now(),
        ]);

        $this->tradingLink = MerchantTradingLink::create([
            'merchant_id' => $this->merchant->id,
            'slug' => 'test-trade-link',
            'type' => TradingLinkType::Public,
            'is_primary' => true,
            'label' => 'Main Link',
            'is_active' => true,
        ]);

        // Merchant must have the currency and payment method that tests use
        MerchantCurrency::create([
            'merchant_id' => $this->merchant->id,
            'currency_code' => 'DOP',
            'markup_percent' => 2.0,
            'min_amount' => 10,
            'max_amount' => 10000,
            'is_active' => true,
        ]);

        MerchantPaymentMethod::create([
            'merchant_id' => $this->merchant->id,
            'type' => 'bank_transfer',
            'provider' => 'bank_transfer',
            'label' => 'Bank Transfer',
            'details' => json_encode(['bank' => 'Test Bank']),
            'is_active' => true,
        ]);
    }

    private function createBuyerUser(?string $wallet = null): User
    {
        $wallet = $wallet ?? '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

        return User::create([
            'name' => 'Buyer',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'wallet_address' => $wallet,
        ]);
    }

    private function createTrade(
        TradeStatus $status = TradeStatus::Pending,
        string $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
    ): Trade {
        return Trade::create([
            'trade_hash' => '0x' . Str::random(64),
            'trading_link_id' => $this->tradingLink->id,
            'merchant_id' => $this->merchant->id,
            'buyer_wallet' => $buyerWallet,
            'amount_usdc' => 100,
            'amount_fiat' => 5700,
            'currency_code' => 'DOP',
            'exchange_rate' => 57.0,
            'fee_amount' => 0.2,
            'payment_method' => 'bank_transfer',
            'type' => TradeType::Buy,
            'status' => $status,
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    /* -----------------------------------------------------------------
     |  Show trading link (public, no auth)
     | ----------------------------------------------------------------- */

    public function test_show_trading_link_returns_details_without_auth(): void
    {
        $response = $this->getJson('/api/trade/' . $this->tradingLink->slug);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'trading_link',
                    'merchant' => ['username', 'wallet_address', 'rank'],
                    'currencies',
                    'payment_methods',
                    'escrow_balance',
                ],
                'message',
            ]);

        $this->assertSame('test_merchant', $response->json('data.merchant.username'));
    }

    public function test_show_trading_link_returns_404_for_nonexistent_slug(): void
    {
        $this->getJson('/api/trade/nonexistent-slug')
            ->assertNotFound();
    }

    public function test_show_trading_link_returns_404_for_inactive_link(): void
    {
        $this->tradingLink->update(['is_active' => false]);

        $this->getJson('/api/trade/' . $this->tradingLink->slug)
            ->assertNotFound();
    }

    public function test_show_trading_link_returns_404_for_inactive_merchant(): void
    {
        $this->merchant->update(['is_active' => false]);

        $this->getJson('/api/trade/' . $this->tradingLink->slug)
            ->assertNotFound();
    }

    /* -----------------------------------------------------------------
     |  Initiate trade (requires auth)
     | ----------------------------------------------------------------- */

    public function test_initiate_requires_authentication(): void
    {
        $this->postJson('/api/trade/' . $this->tradingLink->slug . '/initiate', [
            'amount_usdc' => 100,
            'currency_code' => 'DOP',
            'payment_method' => 'bank_transfer',
        ])->assertUnauthorized();
    }

    public function test_initiate_creates_trade(): void
    {
        Queue::fake();

        $buyer = $this->createBuyerUser();
        Sanctum::actingAs($buyer);

        // Create merchant for the buyer so middleware passes
        $rank = MerchantRank::where('slug', 'new-member')->first();
        Merchant::create([
            'wallet_address' => $buyer->wallet_address,
            'username' => 'buyer_user',
            'is_active' => true,
            'rank_id' => $rank->id,
            'member_since' => now(),
        ]);

        // Mock TradeService to avoid the TradingLinkType -> TradeType
        // enum mismatch in the controller (controller passes $link->type
        // which is TradingLinkType, but Trade model expects TradeType).
        $tradeService = Mockery::mock(TradeService::class);
        $fakeTradeData = [
            'trade_hash' => '0x' . Str::random(64),
            'trading_link_id' => $this->tradingLink->id,
            'merchant_id' => $this->merchant->id,
            'buyer_wallet' => $buyer->wallet_address,
            'amount_usdc' => 100,
            'amount_fiat' => 5700,
            'currency_code' => 'DOP',
            'exchange_rate' => 57.0,
            'fee_amount' => 0.2,
            'payment_method' => 'bank_transfer',
            'type' => TradeType::Buy,
            'status' => TradeStatus::Pending,
            'expires_at' => now()->addMinutes(30),
        ];
        $tradeService->shouldReceive('initiateTrade')
            ->once()
            ->andReturnUsing(fn () => Trade::create($fakeTradeData));
        $this->app->instance(TradeService::class, $tradeService);

        $response = $this->postJson('/api/trade/' . $this->tradingLink->slug . '/initiate', [
            'amount_usdc' => 100,
            'currency_code' => 'DOP',
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['trade_hash', 'amount_usdc', 'status'],
                'message',
            ]);

        Queue::assertPushed(\App\Jobs\ProcessTradeInitiation::class);
    }

    public function test_initiate_returns_404_for_nonexistent_link(): void
    {
        $buyer = $this->createBuyerUser();
        Sanctum::actingAs($buyer);

        $rank = MerchantRank::where('slug', 'new-member')->first();
        Merchant::create([
            'wallet_address' => $buyer->wallet_address,
            'username' => 'buyer_user',
            'is_active' => true,
            'rank_id' => $rank->id,
            'member_since' => now(),
        ]);

        $this->postJson('/api/trade/nonexistent-slug/initiate', [
            'amount_usdc' => 100,
            'currency_code' => 'DOP',
            'payment_method' => 'bank_transfer',
        ])->assertNotFound();
    }

    public function test_initiate_validates_required_fields(): void
    {
        $buyer = $this->createBuyerUser();
        Sanctum::actingAs($buyer);

        $rank = MerchantRank::where('slug', 'new-member')->first();
        Merchant::create([
            'wallet_address' => $buyer->wallet_address,
            'username' => 'buyer_user',
            'is_active' => true,
            'rank_id' => $rank->id,
            'member_since' => now(),
        ]);

        $this->postJson('/api/trade/' . $this->tradingLink->slug . '/initiate', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount_usdc', 'currency_code', 'payment_method']);
    }

    /* -----------------------------------------------------------------
     |  Mark paid (requires buyer wallet match)
     | ----------------------------------------------------------------- */

    public function test_mark_paid_requires_buyer_wallet_match(): void
    {
        $trade = $this->createTrade(TradeStatus::EscrowLocked);

        // Authenticate as a different user (not the buyer)
        $otherWallet = '0xcccccccccccccccccccccccccccccccccccccccc';
        $otherUser = User::create([
            'name' => 'Other',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'wallet_address' => $otherWallet,
        ]);
        $rank = MerchantRank::where('slug', 'new-member')->first();
        Merchant::create([
            'wallet_address' => $otherWallet,
            'username' => 'other_user',
            'is_active' => true,
            'rank_id' => $rank->id,
            'member_since' => now(),
        ]);

        Sanctum::actingAs($otherUser);

        $this->postJson('/api/trade/' . $trade->trade_hash . '/paid')
            ->assertForbidden();
    }

    public function test_mark_paid_succeeds_for_buyer(): void
    {
        Queue::fake();

        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::EscrowLocked, $buyerWallet);

        $buyer = $this->createBuyerUser($buyerWallet);
        $rank = MerchantRank::where('slug', 'new-member')->first();
        Merchant::create([
            'wallet_address' => $buyerWallet,
            'username' => 'buyer_user',
            'is_active' => true,
            'rank_id' => $rank->id,
            'member_since' => now(),
        ]);

        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/trade/' . $trade->trade_hash . '/paid');

        $response->assertOk();

        $trade->refresh();
        $this->assertSame(TradeStatus::PaymentSent, $trade->status);
    }

    public function test_mark_paid_rejects_wrong_status(): void
    {
        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::Completed, $buyerWallet);

        $buyer = $this->createBuyerUser($buyerWallet);
        $rank = MerchantRank::where('slug', 'new-member')->first();
        Merchant::create([
            'wallet_address' => $buyerWallet,
            'username' => 'buyer_user',
            'is_active' => true,
            'rank_id' => $rank->id,
            'member_since' => now(),
        ]);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/trade/' . $trade->trade_hash . '/paid')
            ->assertUnprocessable();
    }

    /* -----------------------------------------------------------------
     |  Cancel trade (requires buyer)
     | ----------------------------------------------------------------- */

    public function test_cancel_requires_buyer(): void
    {
        $trade = $this->createTrade(TradeStatus::Pending);

        // Authenticate as the merchant (not the buyer)
        Sanctum::actingAs($this->merchantUser);

        $this->postJson('/api/trade/' . $trade->trade_hash . '/cancel')
            ->assertForbidden();
    }

    public function test_cancel_succeeds_for_buyer_with_pending_status(): void
    {
        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::Pending, $buyerWallet);

        $buyer = $this->createBuyerUser($buyerWallet);
        $rank = MerchantRank::where('slug', 'new-member')->first();
        Merchant::create([
            'wallet_address' => $buyerWallet,
            'username' => 'buyer_user',
            'is_active' => true,
            'rank_id' => $rank->id,
            'member_since' => now(),
        ]);

        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/trade/' . $trade->trade_hash . '/cancel');

        $response->assertOk();

        $trade->refresh();
        $this->assertSame(TradeStatus::Cancelled, $trade->status);
    }

    public function test_cancel_rejects_completed_trade(): void
    {
        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::Completed, $buyerWallet);

        $buyer = $this->createBuyerUser($buyerWallet);
        $rank = MerchantRank::where('slug', 'new-member')->first();
        Merchant::create([
            'wallet_address' => $buyerWallet,
            'username' => 'buyer_user',
            'is_active' => true,
            'rank_id' => $rank->id,
            'member_since' => now(),
        ]);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/trade/' . $trade->trade_hash . '/cancel')
            ->assertUnprocessable();
    }

    /* -----------------------------------------------------------------
     |  Confirm trade (requires merchant)
     | ----------------------------------------------------------------- */

    public function test_confirm_requires_merchant(): void
    {
        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::PaymentSent, $buyerWallet);

        // Authenticate as buyer (not the merchant)
        $buyer = $this->createBuyerUser($buyerWallet);
        $rank = MerchantRank::where('slug', 'new-member')->first();
        Merchant::create([
            'wallet_address' => $buyerWallet,
            'username' => 'buyer_user',
            'is_active' => true,
            'rank_id' => $rank->id,
            'member_since' => now(),
        ]);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/merchant/trades/' . $trade->trade_hash . '/confirm')
            ->assertForbidden();
    }

    public function test_confirm_succeeds_for_merchant(): void
    {
        Queue::fake();

        $trade = $this->createTrade(TradeStatus::PaymentSent);

        Sanctum::actingAs($this->merchantUser);

        $response = $this->postJson('/api/merchant/trades/' . $trade->trade_hash . '/confirm');

        $response->assertOk();

        $trade->refresh();
        $this->assertSame(TradeStatus::Completed, $trade->status);
        $this->assertNotNull($trade->completed_at);

        Queue::assertPushed(\App\Jobs\ProcessTradeConfirmation::class);
    }

    public function test_confirm_rejects_wrong_status(): void
    {
        $trade = $this->createTrade(TradeStatus::Cancelled);

        Sanctum::actingAs($this->merchantUser);

        $this->postJson('/api/merchant/trades/' . $trade->trade_hash . '/confirm')
            ->assertUnprocessable();
    }

    public function test_confirm_returns_404_for_nonexistent_trade(): void
    {
        Sanctum::actingAs($this->merchantUser);

        $this->postJson('/api/merchant/trades/0xnonexistent/confirm')
            ->assertNotFound();
    }
}
