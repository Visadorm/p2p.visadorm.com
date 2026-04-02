<?php

namespace Tests\Feature\Api;

use App\Enums\TradeStatus;
use App\Enums\TradeType;
use App\Models\Merchant;
use App\Models\MerchantRank;
use App\Models\Trade;
use App\Models\User;
use Database\Seeders\MerchantRankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MerchantControllerTest extends TestCase
{
    use RefreshDatabase;

    private Merchant $merchant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MerchantRankSeeder::class);

        $walletAddress = '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $rank = MerchantRank::where('slug', 'junior-member')->first();

        $this->user = User::create([
            'name' => 'Test Merchant',
            'email' => 'merchant@test.com',
            'password' => Hash::make('password'),
            'wallet_address' => $walletAddress,
        ]);

        $this->merchant = Merchant::create([
            'wallet_address' => $walletAddress,
            'username' => 'test_merchant',
            'email' => 'merchant@test.com',
            'bio' => 'Test bio',
            'is_active' => true,
            'rank_id' => $rank->id,
            'total_trades' => 50,
            'total_volume' => 25000,
            'completion_rate' => 95.50,
            'reliability_score' => 8.5,
            'dispute_rate' => 1.00,
            'avg_response_minutes' => 5,
            'member_since' => now(),
        ]);
    }

    /* -----------------------------------------------------------------
     |  Dashboard
     | ----------------------------------------------------------------- */

    public function test_dashboard_returns_stats(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/merchant/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'merchant',
                    'stats' => [
                        'total_trades',
                        'total_volume',
                        'completion_rate',
                        'reliability_score',
                        'dispute_rate',
                        'avg_response_minutes',
                    ],
                    'active_trades_count',
                    'open_disputes_count',
                    'escrow_balance',
                ],
                'message',
            ]);

        $stats = $response->json('data.stats');
        $this->assertSame(50, $stats['total_trades']);
        $this->assertSame(5, $stats['avg_response_minutes']);
    }

    public function test_dashboard_counts_active_trades(): void
    {
        // Create active trades
        $this->createTrade(TradeStatus::Pending);
        $this->createTrade(TradeStatus::EscrowLocked);
        $this->createTrade(TradeStatus::PaymentSent);

        // Non-active trades
        $this->createTrade(TradeStatus::Completed);
        $this->createTrade(TradeStatus::Cancelled);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/merchant/dashboard');

        $response->assertOk();
        $this->assertSame(3, $response->json('data.active_trades_count'));
    }

    public function test_dashboard_counts_open_disputes(): void
    {
        $this->createTrade(TradeStatus::Disputed);
        $this->createTrade(TradeStatus::Disputed);
        $this->createTrade(TradeStatus::Completed);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/merchant/dashboard');

        $response->assertOk();
        $this->assertSame(2, $response->json('data.open_disputes_count'));
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/merchant/dashboard')
            ->assertUnauthorized();
    }

    /* -----------------------------------------------------------------
     |  Update Profile
     | ----------------------------------------------------------------- */

    public function test_update_profile(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson('/api/merchant/profile', [
            'username' => 'updated_merchant',
            'bio' => 'Updated bio text',
            'trade_timer_minutes' => 45,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.username', 'updated_merchant')
            ->assertJsonPath('data.bio', 'Updated bio text')
            ->assertJsonPath('data.trade_timer_minutes', 45);

        $this->merchant->refresh();
        $this->assertSame('updated_merchant', $this->merchant->username);
        $this->assertSame('Updated bio text', $this->merchant->bio);
        $this->assertSame(45, $this->merchant->trade_timer_minutes);
    }

    public function test_update_profile_validates_username_uniqueness(): void
    {
        // Create another merchant with the target username
        $otherWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $rank = MerchantRank::where('slug', 'new-member')->first();

        Merchant::create([
            'wallet_address' => $otherWallet,
            'username' => 'taken_username',
            'is_active' => true,
            'rank_id' => $rank->id,
            'member_since' => now(),
        ]);

        Sanctum::actingAs($this->user);

        $this->putJson('/api/merchant/profile', [
            'username' => 'taken_username',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('username');
    }

    public function test_update_profile_allows_keeping_own_username(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson('/api/merchant/profile', [
            'username' => 'test_merchant',
        ]);

        $response->assertOk();
    }

    public function test_update_profile_validates_trade_timer_range(): void
    {
        Sanctum::actingAs($this->user);

        $this->putJson('/api/merchant/profile', [
            'trade_timer_minutes' => 5,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('trade_timer_minutes');

        $this->putJson('/api/merchant/profile', [
            'trade_timer_minutes' => 200,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('trade_timer_minutes');
    }

    public function test_update_profile_validates_email(): void
    {
        Sanctum::actingAs($this->user);

        $this->putJson('/api/merchant/profile', [
            'email' => 'not-an-email',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_update_profile_requires_authentication(): void
    {
        $this->putJson('/api/merchant/profile', [
            'username' => 'new_name',
        ])->assertUnauthorized();
    }

    /* -----------------------------------------------------------------
     |  Public Profile
     | ----------------------------------------------------------------- */

    public function test_public_profile_returns_merchant_data(): void
    {
        $response = $this->getJson('/api/merchant/test_merchant/profile');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'merchant' => [
                        'username',
                        'wallet_address',
                        'bio',
                        'rank',
                        'total_trades',
                        'total_volume',
                        'completion_rate',
                        'reliability_score',
                        'currencies',
                        'payment_methods',
                        'trading_links',
                        'escrow_balance',
                        'avg_rating',
                        'review_count',
                    ],
                ],
                'message',
            ]);

        $this->assertSame('test_merchant', $response->json('data.merchant.username'));
    }

    public function test_public_profile_returns_404_for_nonexistent_merchant(): void
    {
        $this->getJson('/api/merchant/nonexistent_user/profile')
            ->assertNotFound();
    }

    public function test_public_profile_returns_404_for_inactive_merchant(): void
    {
        $this->merchant->update(['is_active' => false]);

        $this->getJson('/api/merchant/test_merchant/profile')
            ->assertNotFound();
    }

    public function test_public_profile_does_not_require_authentication(): void
    {
        // No auth header, should still work
        $this->getJson('/api/merchant/test_merchant/profile')
            ->assertOk();
    }

    /* -----------------------------------------------------------------
     |  Helpers
     | ----------------------------------------------------------------- */

    private function createTrade(TradeStatus $status): Trade
    {
        return Trade::create([
            'trade_hash' => '0x' . Str::random(64),
            'merchant_id' => $this->merchant->id,
            'buyer_wallet' => '0x' . fake()->sha1(),
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
}
