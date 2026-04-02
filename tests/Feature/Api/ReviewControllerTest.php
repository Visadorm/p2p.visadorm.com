<?php

namespace Tests\Feature\Api;

use App\Enums\TradeStatus;
use App\Enums\TradeType;
use App\Enums\TradingLinkType;
use App\Models\Merchant;
use App\Models\MerchantRank;
use App\Models\MerchantTradingLink;
use App\Models\Review;
use App\Models\Trade;
use App\Models\User;
use Database\Seeders\MerchantRankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewControllerTest extends TestCase
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

    private function createMerchantForUser(User $user): Merchant
    {
        $rank = MerchantRank::where('slug', 'new-member')->first();

        return Merchant::create([
            'wallet_address' => $user->wallet_address,
            'username' => 'user_' . Str::random(6),
            'is_active' => true,
            'rank_id' => $rank->id,
            'member_since' => now(),
        ]);
    }

    private function createTrade(
        TradeStatus $status = TradeStatus::Completed,
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
     |  store (submit review)
     | ----------------------------------------------------------------- */

    public function test_store_returns_401_for_unauthenticated(): void
    {
        $trade = $this->createTrade(TradeStatus::Completed);

        $this->postJson('/api/trade/' . $trade->trade_hash . '/review', [
            'rating' => 5,
            'comment' => 'Great experience!',
        ])->assertUnauthorized();
    }

    public function test_store_returns_403_if_user_is_not_the_buyer(): void
    {
        $trade = $this->createTrade(TradeStatus::Completed);

        // Authenticate as the merchant (not the buyer)
        Sanctum::actingAs($this->merchantUser);

        $this->postJson('/api/trade/' . $trade->trade_hash . '/review', [
            'rating' => 5,
        ])->assertForbidden();
    }

    public function test_store_returns_422_if_trade_status_is_not_completed(): void
    {
        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::PaymentSent, $buyerWallet);

        $buyer = $this->createBuyerUser($buyerWallet);
        $this->createMerchantForUser($buyer);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/trade/' . $trade->trade_hash . '/review', [
            'rating' => 4,
        ])->assertUnprocessable();
    }

    public function test_store_returns_422_if_review_already_exists(): void
    {
        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::Completed, $buyerWallet);

        // Create a pre-existing review
        $trade->review()->create([
            'merchant_id' => $trade->merchant_id,
            'reviewer_wallet' => $buyerWallet,
            'rating' => 5,
            'comment' => 'First review.',
            'created_at' => now(),
        ]);

        $buyer = $this->createBuyerUser($buyerWallet);
        $this->createMerchantForUser($buyer);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/trade/' . $trade->trade_hash . '/review', [
            'rating' => 3,
        ])->assertUnprocessable();
    }

    public function test_store_returns_422_for_invalid_rating_values(): void
    {
        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::Completed, $buyerWallet);

        $buyer = $this->createBuyerUser($buyerWallet);
        $this->createMerchantForUser($buyer);

        Sanctum::actingAs($buyer);

        // rating = 0 is below the minimum of 1
        $this->postJson('/api/trade/' . $trade->trade_hash . '/review', [
            'rating' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['rating']);

        // rating = 6 is above the maximum of 5
        $this->postJson('/api/trade/' . $trade->trade_hash . '/review', [
            'rating' => 6,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_store_returns_201_and_creates_review_for_buyer_on_completed_trade(): void
    {
        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::Completed, $buyerWallet);

        $buyer = $this->createBuyerUser($buyerWallet);
        $this->createMerchantForUser($buyer);

        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/trade/' . $trade->trade_hash . '/review', [
            'rating' => 5,
            'comment' => 'Excellent merchant, fast trade!',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['rating', 'comment', 'reviewer_wallet'],
                'message',
            ]);

        $this->assertDatabaseHas('reviews', [
            'trade_id' => $trade->id,
            'merchant_id' => $this->merchant->id,
            'reviewer_wallet' => strtolower($buyerWallet),
            'rating' => 5,
            'comment' => 'Excellent merchant, fast trade!',
        ]);
    }
}
