<?php

namespace Tests\Feature\Api;

use App\Enums\DisputeStatus;
use App\Enums\TradeStatus;
use App\Enums\TradeType;
use App\Enums\TradingLinkType;
use App\Models\Dispute;
use App\Models\Merchant;
use App\Models\MerchantRank;
use App\Models\MerchantTradingLink;
use App\Models\Trade;
use App\Models\User;
use App\Services\DisputeService;
use Database\Seeders\MerchantRankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class DisputeControllerTest extends TestCase
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
        TradeStatus $status = TradeStatus::EscrowLocked,
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
     |  store (open dispute)
     | ----------------------------------------------------------------- */

    public function test_store_returns_401_for_unauthenticated_request(): void
    {
        $trade = $this->createTrade(TradeStatus::EscrowLocked);

        $this->postJson('/api/trade/' . $trade->trade_hash . '/dispute', [
            'reason' => 'Seller is not responding.',
        ])->assertUnauthorized();
    }

    public function test_store_returns_422_for_invalid_trade_status(): void
    {
        // Pending is not EscrowLocked or PaymentSent
        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::Pending, $buyerWallet);

        $buyer = $this->createBuyerUser($buyerWallet);
        $this->createMerchantForUser($buyer);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/trade/' . $trade->trade_hash . '/dispute', [
            'reason' => 'Seller is not responding.',
        ])->assertUnprocessable();
    }

    public function test_store_returns_403_for_user_not_party_to_the_trade(): void
    {
        $trade = $this->createTrade(TradeStatus::EscrowLocked);

        $otherWallet = '0xcccccccccccccccccccccccccccccccccccccccc';
        $otherUser = $this->createBuyerUser($otherWallet);
        $this->createMerchantForUser($otherUser);

        Sanctum::actingAs($otherUser);

        $this->postJson('/api/trade/' . $trade->trade_hash . '/dispute', [
            'reason' => 'Seller is not responding.',
        ])->assertForbidden();
    }

    public function test_store_returns_422_if_dispute_already_exists(): void
    {
        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::EscrowLocked, $buyerWallet);

        // Create a pre-existing dispute
        Dispute::create([
            'trade_id' => $trade->id,
            'opened_by' => $buyerWallet,
            'reason' => 'First dispute',
            'status' => DisputeStatus::Open,
        ]);

        $buyer = $this->createBuyerUser($buyerWallet);
        $this->createMerchantForUser($buyer);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/trade/' . $trade->trade_hash . '/dispute', [
            'reason' => 'Trying again.',
        ])->assertUnprocessable();
    }

    public function test_store_returns_201_and_creates_dispute_for_buyer(): void
    {
        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::EscrowLocked, $buyerWallet);

        $buyer = $this->createBuyerUser($buyerWallet);
        $this->createMerchantForUser($buyer);

        // Create a separate trade so the persisted dispute does not belong to $trade,
        // which would trigger the "dispute already exists" guard on $trade.
        $anotherTrade = $this->createTrade(TradeStatus::EscrowLocked, $buyerWallet);
        $fakeDispute = Dispute::create([
            'trade_id' => $anotherTrade->id,
            'opened_by' => $buyerWallet,
            'reason' => 'Seller is not responding.',
            'status' => DisputeStatus::Open,
            'evidence' => [],
        ]);

        $disputeService = Mockery::mock(DisputeService::class);
        $disputeService->shouldReceive('openDispute')
            ->once()
            ->andReturn($fakeDispute);
        $this->app->instance(DisputeService::class, $disputeService);

        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/trade/' . $trade->trade_hash . '/dispute', [
            'reason' => 'Seller is not responding.',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data',
                'message',
            ]);
    }

    /* -----------------------------------------------------------------
     |  show
     | ----------------------------------------------------------------- */

    public function test_show_returns_404_for_nonexistent_dispute(): void
    {
        Sanctum::actingAs($this->merchantUser);

        $this->getJson('/api/dispute/99999')
            ->assertNotFound();
    }

    public function test_show_returns_403_for_user_not_party_to_trade(): void
    {
        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::Disputed, $buyerWallet);

        $dispute = Dispute::create([
            'trade_id' => $trade->id,
            'opened_by' => $buyerWallet,
            'reason' => 'Disputed.',
            'status' => DisputeStatus::Open,
        ]);

        $otherWallet = '0xcccccccccccccccccccccccccccccccccccccccc';
        $otherUser = $this->createBuyerUser($otherWallet);
        $this->createMerchantForUser($otherUser);

        Sanctum::actingAs($otherUser);

        $this->getJson('/api/dispute/' . $dispute->id)
            ->assertForbidden();
    }

    public function test_show_returns_200_for_buyer(): void
    {
        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::Disputed, $buyerWallet);

        $dispute = Dispute::create([
            'trade_id' => $trade->id,
            'opened_by' => $buyerWallet,
            'reason' => 'Payment not received.',
            'status' => DisputeStatus::Open,
        ]);

        $buyer = $this->createBuyerUser($buyerWallet);
        $this->createMerchantForUser($buyer);

        Sanctum::actingAs($buyer);

        $response = $this->getJson('/api/dispute/' . $dispute->id);

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'message',
            ]);
    }

    public function test_show_returns_200_for_merchant(): void
    {
        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::Disputed, $buyerWallet);

        $dispute = Dispute::create([
            'trade_id' => $trade->id,
            'opened_by' => $buyerWallet,
            'reason' => 'Buyer claims payment was sent.',
            'status' => DisputeStatus::Open,
        ]);

        // Authenticate as the merchant who owns the trade
        Sanctum::actingAs($this->merchantUser);

        $response = $this->getJson('/api/dispute/' . $dispute->id);

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'message',
            ]);
    }

    /* -----------------------------------------------------------------
     |  uploadEvidence
     | ----------------------------------------------------------------- */

    public function test_upload_evidence_returns_403_for_user_not_party(): void
    {
        Storage::fake('private');

        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::Disputed, $buyerWallet);

        $dispute = Dispute::create([
            'trade_id' => $trade->id,
            'opened_by' => $buyerWallet,
            'reason' => 'Test.',
            'status' => DisputeStatus::Open,
        ]);

        $otherWallet = '0xcccccccccccccccccccccccccccccccccccccccc';
        $otherUser = $this->createBuyerUser($otherWallet);
        $this->createMerchantForUser($otherUser);

        Sanctum::actingAs($otherUser);

        $this->postJson('/api/dispute/' . $dispute->id . '/evidence', [
            'file' => UploadedFile::fake()->image('evidence.jpg'),
        ])->assertForbidden();
    }

    public function test_upload_evidence_returns_422_if_dispute_is_not_open(): void
    {
        Storage::fake('private');

        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::Completed, $buyerWallet);

        $dispute = Dispute::create([
            'trade_id' => $trade->id,
            'opened_by' => $buyerWallet,
            'reason' => 'Test.',
            'status' => DisputeStatus::ResolvedBuyer,
        ]);

        $buyer = $this->createBuyerUser($buyerWallet);
        $this->createMerchantForUser($buyer);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/dispute/' . $dispute->id . '/evidence', [
            'file' => UploadedFile::fake()->image('evidence.jpg'),
        ])->assertUnprocessable();
    }

    public function test_upload_evidence_returns_422_if_file_validation_fails(): void
    {
        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::Disputed, $buyerWallet);

        $dispute = Dispute::create([
            'trade_id' => $trade->id,
            'opened_by' => $buyerWallet,
            'reason' => 'Test.',
            'status' => DisputeStatus::Open,
        ]);

        $buyer = $this->createBuyerUser($buyerWallet);
        $this->createMerchantForUser($buyer);

        Sanctum::actingAs($buyer);

        // Send no file
        $this->postJson('/api/dispute/' . $dispute->id . '/evidence', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_upload_evidence_returns_200_for_valid_upload(): void
    {
        Storage::fake('private');

        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $trade = $this->createTrade(TradeStatus::Disputed, $buyerWallet);

        $dispute = Dispute::create([
            'trade_id' => $trade->id,
            'opened_by' => $buyerWallet,
            'reason' => 'Test.',
            'status' => DisputeStatus::Open,
        ]);

        $buyer = $this->createBuyerUser($buyerWallet);
        $this->createMerchantForUser($buyer);

        $disputeService = Mockery::mock(DisputeService::class);
        $disputeService->shouldReceive('submitEvidence')
            ->once()
            ->andReturn($dispute->fresh());
        $this->app->instance(DisputeService::class, $disputeService);

        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/dispute/' . $dispute->id . '/evidence', [
            'file' => UploadedFile::fake()->image('evidence.jpg'),
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'message',
            ]);
    }

    public function test_upload_evidence_returns_404_for_nonexistent_dispute(): void
    {
        Storage::fake('private');

        $buyer = $this->createBuyerUser();
        $this->createMerchantForUser($buyer);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/dispute/99999/evidence', [
            'file' => UploadedFile::fake()->image('evidence.jpg'),
        ])->assertNotFound();
    }
}
