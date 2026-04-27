<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\TradeStatus;
use App\Enums\TradeType;
use App\Models\Merchant;
use App\Models\MerchantCurrency;
use App\Models\MerchantPaymentMethod;
use App\Models\MerchantRank;
use App\Models\Trade;
use App\Models\User;
use App\Services\BlockchainService;
use App\Services\DisputeService;
use App\Settings\TradeSettings;
use Database\Seeders\MerchantRankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class SellTradeFlowTest extends TestCase
{
    use RefreshDatabase;

    private Merchant $merchant;
    private User $merchantUser;
    private Merchant $sellerMerchant;
    private User $sellerUser;
    private MerchantPaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MerchantRankSeeder::class);

        // Seed blockchain settings so BlockchainSettings hydrates in tests
        $chain = app(\App\Settings\BlockchainSettings::class);
        $chain->trade_escrow_address = '0x75B60DD962370d5569cDfe97F52833882B9ae66B';
        $chain->save();

        $rank = MerchantRank::where('slug', 'new-member')->first();

        // Merchant (the buyer-of-USDC counterparty)
        $merchantWallet = '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $this->merchantUser = User::create([
            'name' => 'Merchant',
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

        MerchantCurrency::create([
            'merchant_id' => $this->merchant->id,
            'currency_code' => 'USD',
            'markup_percent' => 0,
            'min_amount' => 10,
            'max_amount' => 10000,
            'is_active' => true,
        ]);

        $this->paymentMethod = MerchantPaymentMethod::create([
            'merchant_id' => $this->merchant->id,
            'type' => 'bank_transfer',
            'provider' => 'bank_transfer',
            'label' => 'Bank Transfer',
            'details' => json_encode(['bank' => 'Test Bank']),
            'is_active' => true,
        ]);

        // Seller (the user with USDC) — needs own merchant account for auth
        $sellerWallet = '0xcccccccccccccccccccccccccccccccccccccccc';
        $this->sellerUser = User::create([
            'name' => 'Seller',
            'email' => 'seller@test.com',
            'password' => Hash::make('password'),
            'wallet_address' => $sellerWallet,
        ]);
        $this->sellerMerchant = Merchant::create([
            'wallet_address' => $sellerWallet,
            'username' => 'test_seller',
            'is_active' => true,
            'rank_id' => $rank->id,
            'total_trades' => 0,
            'total_volume' => 0,
            'completion_rate' => 100,
            'member_since' => now(),
        ]);
    }

    private function actingAsSeller(): self
    {
        Sanctum::actingAs($this->sellerUser);
        return $this;
    }

    private function actingAsMerchant(): self
    {
        Sanctum::actingAs($this->merchantUser);
        return $this;
    }

    private function mockBlockchain(array $stub = []): BlockchainService
    {
        $mock = Mockery::mock(BlockchainService::class);
        $defaults = [
            'openSellTradeCalldata' => '0xdeadbeef',
            'getTransactionReceipt' => null,
            'parseSellTradeOpenedLog' => null,
            'parseSellTradeJoinedLog' => null,
            'parseSellPaymentMarkedLog' => null,
            'parseSellEscrowReleasedLog' => null,
            'parseDisputeOpenedLog' => null,
            'parseTradeCancelledLog' => null,
        ];
        foreach (array_merge($defaults, $stub) as $method => $return) {
            $mock->shouldReceive($method)->andReturn($return)->byDefault();
        }
        $this->app->instance(BlockchainService::class, $mock);
        return $mock;
    }

    private function makeSellTrade(TradeStatus $status = TradeStatus::Pending, array $extra = []): Trade
    {
        return Trade::create(array_merge([
            'trade_hash' => '0x' . Str::random(64),
            'merchant_id' => $this->merchant->id,
            'seller_wallet' => strtolower($this->sellerMerchant->wallet_address),
            'buyer_wallet' => strtolower($this->merchant->wallet_address),
            'amount_usdc' => 100,
            'amount_fiat' => 100,
            'currency_code' => 'USD',
            'exchange_rate' => 1.0,
            'fee_amount' => 0.2,
            'payment_method' => (string) $this->paymentMethod->id,
            'is_cash_trade' => false,
            'type' => TradeType::Sell,
            'status' => $status,
            'stake_amount' => 5,
            'expires_at' => now()->addMinutes(60),
        ], $extra));
    }

    private function escrowAddress(): string
    {
        return strtolower(app(\App\Settings\BlockchainSettings::class)->trade_escrow_address ?: '0xdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef');
    }

    private function fakeReceipt(string $from, ?string $to = null): array
    {
        return [
            'status' => '0x1',
            'from' => strtolower($from),
            'to' => $to ?? $this->escrowAddress(),
            'logs' => [],
        ];
    }

    // ─── B01-B05: store ───────────────────────────────────────────

    public function test_b01_store_returns_calldata_and_creates_db_row(): void
    {
        $this->mockBlockchain(['openSellTradeCalldata' => '0xabcd']);
        $this->actingAsSeller();

        $res = $this->postJson('/api/sell-trades', [
            'merchant_wallet' => $this->merchant->wallet_address,
            'amount' => 100,
            'currency' => 'USD',
            'payment_method_id' => $this->paymentMethod->id,
            'fiat_rate' => 1.0,
            'expires_at' => now()->addHour()->toIso8601String(),
            'is_cash_trade' => false,
        ]);

        $res->assertCreated()
            ->assertJsonStructure(['data' => ['trade_hash', 'trade_id', 'calldata', 'escrow_address', 'approve_amount', 'expires_at', 'stake_required', 'stake_amount_usdc']]);
        $this->assertDatabaseHas('trades', ['type' => 'sell', 'status' => 'pending']);
    }

    public function test_b02_store_rejects_bad_merchant_wallet(): void
    {
        $this->mockBlockchain();
        $this->actingAsSeller();
        $this->postJson('/api/sell-trades', [
            'merchant_wallet' => 'not-a-wallet',
            'amount' => 100,
            'currency' => 'USD',
            'payment_method_id' => $this->paymentMethod->id,
            'fiat_rate' => 1.0,
        ])->assertStatus(422);
    }

    public function test_b03_store_rejects_when_sell_disabled(): void
    {
        $this->mockBlockchain();
        $settings = app(TradeSettings::class);
        $settings->sell_enabled = false;
        $settings->save();

        $this->actingAsSeller();
        $res = $this->postJson('/api/sell-trades', [
            'merchant_wallet' => $this->merchant->wallet_address,
            'amount' => 100,
            'currency' => 'USD',
            'payment_method_id' => $this->paymentMethod->id,
            'fiat_rate' => 1.0,
        ]);
        $res->assertStatus(422);

        $settings->sell_enabled = true;
        $settings->save();
    }

    public function test_b04_store_rejects_cash_when_disabled(): void
    {
        $this->mockBlockchain();
        $settings = app(TradeSettings::class);
        $settings->sell_cash_trade_enabled = false;
        $settings->save();

        $this->actingAsSeller();
        $res = $this->postJson('/api/sell-trades', [
            'merchant_wallet' => $this->merchant->wallet_address,
            'amount' => 100,
            'currency' => 'USD',
            'payment_method_id' => $this->paymentMethod->id,
            'fiat_rate' => 1.0,
            'is_cash_trade' => true,
        ]);
        $res->assertStatus(422);

        $settings->sell_cash_trade_enabled = true;
        $settings->save();
    }

    public function test_b05_store_rejects_amount_below_min(): void
    {
        $this->mockBlockchain();
        $settings = app(TradeSettings::class);
        $settings->global_min_trade = 50;
        $settings->save();

        $this->actingAsSeller();
        $this->postJson('/api/sell-trades', [
            'merchant_wallet' => $this->merchant->wallet_address,
            'amount' => 10,
            'currency' => 'USD',
            'payment_method_id' => $this->paymentMethod->id,
            'fiat_rate' => 1.0,
        ])->assertStatus(422);

        $settings->global_min_trade = 1;
        $settings->save();
    }

    // ─── B06-B08: confirmFund ─────────────────────────────────────

    public function test_b06_confirm_fund_rejects_bad_tx_hash(): void
    {
        $this->mockBlockchain();
        $trade = $this->makeSellTrade();
        $this->actingAsSeller();
        $this->postJson("/api/sell-trades/{$trade->trade_hash}/confirm-fund", ['fund_tx_hash' => 'not-a-hash'])
            ->assertStatus(422);
    }

    public function test_b07_confirm_fund_rejects_when_receipt_invalid(): void
    {
        $this->mockBlockchain([
            'getTransactionReceipt' => null, // no receipt
        ]);
        $trade = $this->makeSellTrade();
        $this->actingAsSeller();
        $this->postJson("/api/sell-trades/{$trade->trade_hash}/confirm-fund", [
            'fund_tx_hash' => '0x' . str_repeat('a', 64),
        ])->assertStatus(422);
    }

    public function test_b08_confirm_fund_idempotent(): void
    {
        $hash = '0x' . str_repeat('b', 64);
        $this->mockBlockchain([
            'getTransactionReceipt' => $this->fakeReceipt($this->sellerMerchant->wallet_address),
            'parseSellTradeOpenedLog' => ['topics' => [], 'data' => '0x'],
        ]);
        $trade = $this->makeSellTrade();
        $this->actingAsSeller();
        $this->postJson("/api/sell-trades/{$trade->trade_hash}/confirm-fund", ['fund_tx_hash' => $hash])
            ->assertOk();
        $this->postJson("/api/sell-trades/{$trade->trade_hash}/confirm-fund", ['fund_tx_hash' => $hash])
            ->assertOk();
    }

    // ─── B09-B11: confirmJoin ─────────────────────────────────────

    public function test_b09_confirm_join_validates_from_merchant(): void
    {
        Event::fake();
        $this->mockBlockchain([
            'getTransactionReceipt' => $this->fakeReceipt($this->merchant->wallet_address),
            'parseSellTradeJoinedLog' => ['topics' => [], 'data' => '0x'],
        ]);
        $trade = $this->makeSellTrade();
        $this->actingAsMerchant();
        $this->postJson("/api/sell-trades/{$trade->trade_hash}/confirm-join", [
            'join_tx_hash' => '0x' . str_repeat('c', 64),
        ])->assertOk();
        $this->assertDatabaseHas('trades', ['id' => $trade->id, 'status' => 'escrow_locked']);
        Event::assertDispatched(\App\Events\SellTradeJoined::class);
    }

    public function test_b10_confirm_join_rejects_wrong_from(): void
    {
        $this->mockBlockchain([
            'getTransactionReceipt' => $this->fakeReceipt('0x9999999999999999999999999999999999999999'),
            'parseSellTradeJoinedLog' => ['topics' => [], 'data' => '0x'],
        ]);
        $trade = $this->makeSellTrade();
        $this->actingAsMerchant();
        $this->postJson("/api/sell-trades/{$trade->trade_hash}/confirm-join", [
            'join_tx_hash' => '0x' . str_repeat('d', 64),
        ])->assertStatus(422);
    }

    public function test_b11_confirm_join_idempotent(): void
    {
        Event::fake();
        $hash = '0x' . str_repeat('e', 64);
        $this->mockBlockchain([
            'getTransactionReceipt' => $this->fakeReceipt($this->merchant->wallet_address),
            'parseSellTradeJoinedLog' => ['topics' => [], 'data' => '0x'],
        ]);
        $trade = $this->makeSellTrade();
        $this->actingAsMerchant();
        $this->postJson("/api/sell-trades/{$trade->trade_hash}/confirm-join", ['join_tx_hash' => $hash])->assertOk();
        $this->postJson("/api/sell-trades/{$trade->trade_hash}/confirm-join", ['join_tx_hash' => $hash])->assertOk();
    }

    // ─── B12-B13: confirmMarkPaid ─────────────────────────────────

    public function test_b12_confirm_mark_paid_validates_from_merchant(): void
    {
        $this->mockBlockchain([
            'getTransactionReceipt' => $this->fakeReceipt($this->merchant->wallet_address),
            'parseSellPaymentMarkedLog' => ['topics' => [], 'data' => '0x'],
        ]);
        $trade = $this->makeSellTrade(TradeStatus::EscrowLocked);
        $this->actingAsMerchant();
        $this->postJson("/api/sell-trades/{$trade->trade_hash}/confirm-mark-paid", [
            'mark_paid_tx_hash' => '0x' . str_repeat('f', 64),
        ])->assertOk();
        $this->assertDatabaseHas('trades', ['id' => $trade->id, 'status' => 'payment_sent']);
    }

    public function test_b13_confirm_mark_paid_idempotent(): void
    {
        $hash = '0x' . str_repeat('1', 64);
        $this->mockBlockchain([
            'getTransactionReceipt' => $this->fakeReceipt($this->merchant->wallet_address),
            'parseSellPaymentMarkedLog' => ['topics' => [], 'data' => '0x'],
        ]);
        $trade = $this->makeSellTrade(TradeStatus::EscrowLocked);
        $this->actingAsMerchant();
        $this->postJson("/api/sell-trades/{$trade->trade_hash}/confirm-mark-paid", ['mark_paid_tx_hash' => $hash])->assertOk();
        $this->postJson("/api/sell-trades/{$trade->trade_hash}/confirm-mark-paid", ['mark_paid_tx_hash' => $hash])->assertOk();
    }

    // ─── B14-B17: confirmRelease ──────────────────────────────────

    public function test_b14_confirm_release_validates_from_seller(): void
    {
        Event::fake();
        $this->mockBlockchain([
            'getTransactionReceipt' => $this->fakeReceipt($this->sellerMerchant->wallet_address),
            'parseSellEscrowReleasedLog' => ['topics' => [], 'data' => '0x'],
        ]);
        $trade = $this->makeSellTrade(TradeStatus::PaymentSent);
        $this->actingAsSeller();
        $this->postJson("/api/sell-trades/{$trade->trade_hash}/confirm-release", [
            'release_tx_hash' => '0x' . str_repeat('2', 64),
        ])->assertOk();
        $this->assertDatabaseHas('trades', ['id' => $trade->id, 'status' => 'completed']);
    }

    public function test_b15_confirm_release_rejects_wrong_from(): void
    {
        $this->mockBlockchain([
            'getTransactionReceipt' => $this->fakeReceipt($this->merchant->wallet_address),
            'parseSellEscrowReleasedLog' => ['topics' => [], 'data' => '0x'],
        ]);
        $trade = $this->makeSellTrade(TradeStatus::PaymentSent);
        $this->actingAsSeller();
        $this->postJson("/api/sell-trades/{$trade->trade_hash}/confirm-release", [
            'release_tx_hash' => '0x' . str_repeat('3', 64),
        ])->assertStatus(422);
    }

    public function test_b16_confirm_release_idempotent(): void
    {
        Event::fake();
        $hash = '0x' . str_repeat('4', 64);
        $this->mockBlockchain([
            'getTransactionReceipt' => $this->fakeReceipt($this->sellerMerchant->wallet_address),
            'parseSellEscrowReleasedLog' => ['topics' => [], 'data' => '0x'],
        ]);
        $trade = $this->makeSellTrade(TradeStatus::PaymentSent);
        $this->actingAsSeller();
        $this->postJson("/api/sell-trades/{$trade->trade_hash}/confirm-release", ['release_tx_hash' => $hash])->assertOk();
        $this->postJson("/api/sell-trades/{$trade->trade_hash}/confirm-release", ['release_tx_hash' => $hash])->assertOk();
    }

    public function test_b17_confirm_release_fires_trade_completed_event(): void
    {
        Event::fake([\App\Events\TradeCompleted::class]);
        $this->mockBlockchain([
            'getTransactionReceipt' => $this->fakeReceipt($this->sellerMerchant->wallet_address),
            'parseSellEscrowReleasedLog' => ['topics' => [], 'data' => '0x'],
        ]);
        $trade = $this->makeSellTrade(TradeStatus::PaymentSent);
        $this->actingAsSeller();
        $this->postJson("/api/sell-trades/{$trade->trade_hash}/confirm-release", [
            'release_tx_hash' => '0x' . str_repeat('5', 64),
        ])->assertOk();
        Event::assertDispatched(\App\Events\TradeCompleted::class);
    }

    // ─── B18-B20: dispute + cancel ────────────────────────────────

    public function test_b18_dispute_validates_from_party(): void
    {
        Event::fake();
        $this->mockBlockchain([
            'getTransactionReceipt' => $this->fakeReceipt($this->sellerMerchant->wallet_address),
            'parseDisputeOpenedLog' => ['topics' => [], 'data' => '0x'],
        ]);
        // Stub DisputeService to avoid real dispute creation side effects
        $this->app->instance(DisputeService::class, Mockery::mock(DisputeService::class)
            ->shouldReceive('openDispute')->andReturn(new \App\Models\Dispute())->getMock());

        $trade = $this->makeSellTrade(TradeStatus::EscrowLocked);
        $this->actingAsSeller();
        $this->postJson("/api/sell-trades/{$trade->trade_hash}/dispute", [
            'dispute_tx_hash' => '0x' . str_repeat('6', 64),
            'reason' => 'Counterparty stopped responding after marking paid',
        ])->assertOk();
        $this->assertDatabaseHas('trades', ['id' => $trade->id, 'status' => 'disputed']);
    }

    public function test_b19_dispute_records_dispute_tx_hash(): void
    {
        Event::fake();
        $hash = '0x' . str_repeat('7', 64);
        $this->mockBlockchain([
            'getTransactionReceipt' => $this->fakeReceipt($this->sellerMerchant->wallet_address),
            'parseDisputeOpenedLog' => ['topics' => [], 'data' => '0x'],
        ]);
        $this->app->instance(DisputeService::class, Mockery::mock(DisputeService::class)
            ->shouldReceive('openDispute')->andReturn(new \App\Models\Dispute())->getMock());

        $trade = $this->makeSellTrade(TradeStatus::PaymentSent);
        $this->actingAsSeller();
        $this->postJson("/api/sell-trades/{$trade->trade_hash}/dispute", [
            'dispute_tx_hash' => $hash,
            'reason' => 'Reason at least ten chars',
        ])->assertOk();
        $this->assertDatabaseHas('trades', ['id' => $trade->id, 'dispute_tx_hash' => $hash]);
    }

    public function test_b20_cancel_only_works_pre_join(): void
    {
        $this->mockBlockchain([
            'getTransactionReceipt' => $this->fakeReceipt($this->sellerMerchant->wallet_address),
            'parseTradeCancelledLog' => ['topics' => [], 'data' => '0x'],
        ]);

        // Pending → succeeds
        $tradePending = $this->makeSellTrade(TradeStatus::Pending);
        $this->actingAsSeller();
        $this->postJson("/api/sell-trades/{$tradePending->trade_hash}/cancel", [
            'cancel_tx_hash' => '0x' . str_repeat('8', 64),
        ])->assertOk();

        // EscrowLocked → fails
        $tradeJoined = $this->makeSellTrade(TradeStatus::EscrowLocked);
        $this->postJson("/api/sell-trades/{$tradeJoined->trade_hash}/cancel", [
            'cancel_tx_hash' => '0x' . str_repeat('9', 64),
        ])->assertStatus(422);
    }

    // ─── B21-B22: cash proof + verify ─────────────────────────────

    public function test_b21_cash_proof_persists_url_when_cash_trade(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->mockBlockchain();
        $trade = $this->makeSellTrade(TradeStatus::EscrowLocked, ['is_cash_trade' => true]);
        $this->actingAsMerchant();
        $file = \Illuminate\Http\UploadedFile::fake()->image('proof.jpg');
        $this->postJson("/api/sell-trades/{$trade->trade_hash}/cash-proof", ['proof' => $file])
            ->assertOk();
        $this->assertNotNull($trade->fresh()->cash_proof_url);
    }

    public function test_b22_verify_payment_requires_seller_caller(): void
    {
        $this->mockBlockchain();
        $trade = $this->makeSellTrade(TradeStatus::PaymentSent);

        // Merchant attempt — rejected
        $this->actingAsMerchant();
        $this->postJson("/api/sell-trades/{$trade->trade_hash}/verify-payment", ['verified' => true])
            ->assertStatus(422);

        // Seller attempt — accepted
        $this->actingAsSeller();
        $this->postJson("/api/sell-trades/{$trade->trade_hash}/verify-payment", ['verified' => true])
            ->assertOk();
        $this->assertTrue((bool) $trade->fresh()->seller_verified_payment);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
