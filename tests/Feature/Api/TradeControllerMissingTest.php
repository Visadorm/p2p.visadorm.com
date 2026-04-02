<?php

namespace Tests\Feature\Api;

use App\Enums\TradeStatus;
use App\Enums\TradeType;
use App\Enums\TradingLinkType;
use App\Models\Merchant;
use App\Models\MerchantRank;
use App\Models\MerchantTradingLink;
use App\Models\Trade;
use App\Models\User;
use App\Services\TradeService;
use Database\Seeders\MerchantRankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class TradeControllerMissingTest extends TestCase
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
     |  status
     | ----------------------------------------------------------------- */

    public function test_status_returns_404_for_nonexistent_trade_hash(): void
    {
        Sanctum::actingAs($this->merchantUser);

        $this->getJson('/api/trade/0xnonexistent/status')
            ->assertNotFound();
    }

    public function test_status_returns_403_for_user_who_is_neither_buyer_nor_merchant(): void
    {
        $trade = $this->createTrade(TradeStatus::Pending);

        $otherWallet = '0xcccccccccccccccccccccccccccccccccccccccc';
        $otherUser = $this->createBuyerUser($otherWallet);
        $this->createMerchantForUser($otherUser);

        Sanctum::actingAs($otherUser);

        $this->getJson('/api/trade/' . $trade->trade_hash . '/status')
            ->assertForbidden();
    }

    public function test_status_returns_200_with_trade_data_for_buyer(): void
    {
        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::EscrowLocked, $buyerWallet);

        $buyer = $this->createBuyerUser($buyerWallet);
        $this->createMerchantForUser($buyer);

        Sanctum::actingAs($buyer);

        $response = $this->getJson('/api/trade/' . $trade->trade_hash . '/status');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'message',
            ]);
    }

    public function test_status_returns_200_with_trade_data_for_merchant(): void
    {
        $trade = $this->createTrade(TradeStatus::PaymentSent);

        Sanctum::actingAs($this->merchantUser);

        $response = $this->getJson('/api/trade/' . $trade->trade_hash . '/status');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'message',
            ]);
    }

    /* -----------------------------------------------------------------
     |  uploadBankProof
     | ----------------------------------------------------------------- */

    public function test_upload_bank_proof_returns_403_if_user_is_not_the_buyer(): void
    {
        Storage::fake('private');

        $trade = $this->createTrade(TradeStatus::EscrowLocked);

        // Authenticate as the merchant (not the buyer)
        Sanctum::actingAs($this->merchantUser);

        $this->postJson('/api/trade/' . $trade->trade_hash . '/bank-proof', [
            'bank_proof' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertForbidden();
    }

    public function test_upload_bank_proof_returns_404_if_trade_not_found(): void
    {
        Storage::fake('private');

        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $buyer = $this->createBuyerUser($buyerWallet);
        $this->createMerchantForUser($buyer);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/trade/0xnonexistent/bank-proof', [
            'bank_proof' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertNotFound();
    }

    public function test_upload_bank_proof_returns_422_if_file_field_is_missing(): void
    {
        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::EscrowLocked, $buyerWallet);

        $buyer = $this->createBuyerUser($buyerWallet);
        $this->createMerchantForUser($buyer);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/trade/' . $trade->trade_hash . '/bank-proof', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bank_proof']);
    }

    public function test_upload_bank_proof_returns_200_and_calls_trade_service(): void
    {
        Storage::fake('private');

        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::EscrowLocked, $buyerWallet);

        $buyer = $this->createBuyerUser($buyerWallet);
        $this->createMerchantForUser($buyer);

        $tradeService = Mockery::mock(TradeService::class);
        $tradeService->shouldReceive('uploadBankProof')
            ->once()
            ->andReturn($trade->fresh());
        $this->app->instance(TradeService::class, $tradeService);

        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/trade/' . $trade->trade_hash . '/bank-proof', [
            'bank_proof' => UploadedFile::fake()->image('proof.jpg'),
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'message',
            ]);
    }

    /* -----------------------------------------------------------------
     |  uploadBuyerId
     | ----------------------------------------------------------------- */

    public function test_upload_buyer_id_returns_403_if_user_is_not_the_buyer(): void
    {
        Storage::fake('private');

        $trade = $this->createTrade(TradeStatus::EscrowLocked);

        // Authenticate as the merchant (not the buyer)
        Sanctum::actingAs($this->merchantUser);

        $this->postJson('/api/trade/' . $trade->trade_hash . '/buyer-id', [
            'buyer_id' => UploadedFile::fake()->image('id.jpg'),
        ])->assertForbidden();
    }

    public function test_upload_buyer_id_returns_422_if_file_field_is_missing(): void
    {
        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::EscrowLocked, $buyerWallet);

        $buyer = $this->createBuyerUser($buyerWallet);
        $this->createMerchantForUser($buyer);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/trade/' . $trade->trade_hash . '/buyer-id', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['buyer_id']);
    }

    public function test_upload_buyer_id_returns_200_and_calls_trade_service(): void
    {
        Storage::fake('private');

        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::EscrowLocked, $buyerWallet);

        $buyer = $this->createBuyerUser($buyerWallet);
        $this->createMerchantForUser($buyer);

        $tradeService = Mockery::mock(TradeService::class);
        $tradeService->shouldReceive('uploadBuyerId')
            ->once()
            ->andReturn($trade->fresh());
        $this->app->instance(TradeService::class, $tradeService);

        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/trade/' . $trade->trade_hash . '/buyer-id', [
            'buyer_id' => UploadedFile::fake()->image('id.jpg'),
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'message',
            ]);
    }
}
