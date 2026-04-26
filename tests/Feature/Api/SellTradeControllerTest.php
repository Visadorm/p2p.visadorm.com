<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\TradeStatus;
use App\Enums\TradeType;
use App\Models\Merchant;
use App\Models\MerchantPaymentMethod;
use App\Models\MerchantRank;
use App\Models\SellOffer;
use App\Models\Trade;
use App\Models\User;
use App\Events\PaymentMarked;
use App\Events\TradeInitiated;
use App\Services\BlockchainService;
use App\Settings\TradeSettings;
use Database\Seeders\MerchantRankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class SellTradeControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $sellerUser;
    private Merchant $sellerMerchant;
    private User $buyerUser;
    private Merchant $buyerMerchant;
    private SellOffer $offer;
    private MerchantPaymentMethod $sellerPaymentMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MerchantRankSeeder::class);
        $rank = MerchantRank::where('slug', 'junior-member')->first();

        $sellerWallet = '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $this->sellerUser = User::create([
            'name' => 'Seller', 'email' => 's@test.com',
            'password' => Hash::make('password'),
            'wallet_address' => $sellerWallet,
        ]);
        $this->sellerMerchant = Merchant::create([
            'wallet_address' => $sellerWallet, 'username' => 'seller_x',
            'is_active' => true, 'rank_id' => $rank->id, 'kyc_status' => 'approved',
            'member_since' => now(),
        ]);

        $buyerWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $this->buyerUser = User::create([
            'name' => 'Buyer', 'email' => 'b@test.com',
            'password' => Hash::make('password'),
            'wallet_address' => $buyerWallet,
        ]);
        $this->buyerMerchant = Merchant::create([
            'wallet_address' => $buyerWallet, 'username' => 'buyer_x',
            'is_active' => true, 'rank_id' => $rank->id,
            'member_since' => now(),
        ]);

        $this->sellerPaymentMethod = MerchantPaymentMethod::create([
            'merchant_id' => $this->sellerMerchant->id,
            'type' => 'bank_transfer',
            'provider' => 'bank',
            'label' => 'BHD Bank',
            'details' => ['account_number' => '1234567890'],
            'safety_note' => 'Use trade hash as reference',
            'is_active' => true,
        ]);

        $this->offer = SellOffer::create([
            'slug' => 'tradetest123',
            'trade_id' => '0x' . str_repeat('a', 64),
            'seller_wallet' => $sellerWallet,
            'seller_merchant_id' => $this->sellerMerchant->id,
            'amount_usdc' => 500, 'amount_remaining_usdc' => 500,
            'min_trade_usdc' => 500, 'max_trade_usdc' => 500,
            'currency_code' => 'DOP', 'fiat_rate' => 62,
            'payment_methods' => [[
                'merchant_payment_method_id' => $this->sellerPaymentMethod->id,
                'label' => 'BHD Bank', 'provider' => 'bank', 'type' => 'bank_transfer',
            ]],
            'is_active' => true, 'is_private' => false,
            'fund_tx_hash' => '0x' . str_repeat('b', 64),
            'expires_at' => now()->addHours(2),
        ]);

        app(TradeSettings::class)->fill([
            'sell_enabled' => true,
            'sell_max_offers_per_wallet' => 5,
            'sell_max_outstanding_usdc' => 50000,
            'sell_kyc_threshold_usdc' => 1000,
            'sell_kyc_threshold_window_days' => 30,
            'sell_cash_meeting_enabled' => false,
            'sell_default_offer_timer_minutes' => 60,
            'default_trade_timer_minutes' => 30,
        ])->save();
    }

    private function takeOfferAsBuyer(int $amount = 500): Trade
    {
        Sanctum::actingAs($this->buyerUser);
        $response = $this->postJson('/api/sell-offer/' . $this->offer->slug . '/take', [
            'merchant_payment_method_id' => $this->sellerPaymentMethod->id,
            'take_tx_hash' => '0x' . str_repeat('c', 64),
        ])->assertCreated();

        return Trade::where('trade_hash', $response->json('data.trade_hash'))->first();
    }

    public function test_take_creates_a_sell_trade(): void
    {
        $trade = $this->takeOfferAsBuyer();
        $this->assertEquals(TradeType::Sell, $trade->type);
        $this->assertEquals(TradeStatus::EscrowLocked, $trade->status);
        $this->assertEquals(strtolower($this->buyerMerchant->wallet_address), $trade->buyer_wallet);
        $this->assertEquals(strtolower($this->sellerMerchant->wallet_address), $trade->seller_wallet);
        $this->assertEquals('500.000000', (string) $trade->amount_usdc);
        $this->assertFalse((bool) $this->offer->fresh()->is_active);
    }

    public function test_take_uses_offer_trade_id_as_on_chain_hash(): void
    {
        $trade = $this->takeOfferAsBuyer();
        $this->assertEquals($this->offer->trade_id, $trade->trade_hash);
    }

    public function test_take_rejects_seller_taking_own_offer(): void
    {
        Sanctum::actingAs($this->sellerUser);
        $this->postJson('/api/sell-offer/' . $this->offer->slug . '/take', [
            'merchant_payment_method_id' => $this->sellerPaymentMethod->id,
            'take_tx_hash' => '0x' . str_repeat('d', 64),
        ])->assertStatus(422);
    }

    public function test_take_requires_take_tx_hash(): void
    {
        Sanctum::actingAs($this->buyerUser);
        $this->postJson('/api/sell-offer/' . $this->offer->slug . '/take', [
            'merchant_payment_method_id' => $this->sellerPaymentMethod->id,
        ])->assertStatus(422);
    }

    public function test_mark_paid_only_by_buyer(): void
    {
        $trade = $this->takeOfferAsBuyer();

        Sanctum::actingAs($this->sellerUser);
        $this->postJson("/api/trade/{$trade->trade_hash}/sell/mark-paid")
            ->assertStatus(422);

        Sanctum::actingAs($this->buyerUser);
        $this->postJson("/api/trade/{$trade->trade_hash}/sell/mark-paid")
            ->assertOk()
            ->assertJsonPath('data.status', 'payment_sent');
    }

    public function test_release_payload_only_by_seller(): void
    {
        $trade = $this->takeOfferAsBuyer();

        $mock = Mockery::mock(BlockchainService::class)->makePartial();
        $mock->shouldReceive('getSellerNonce')->andReturn(7);
        $this->app->instance(BlockchainService::class, $mock);

        Sanctum::actingAs($this->buyerUser);
        $this->getJson("/api/trade/{$trade->trade_hash}/sell/release-payload")
            ->assertStatus(422);

        Sanctum::actingAs($this->sellerUser);
        $this->getJson("/api/trade/{$trade->trade_hash}/sell/release-payload")
            ->assertOk()
            ->assertJsonPath('data.message.nonce', 7)
            ->assertJsonPath('data.primaryType', 'ReleaseSellEscrow');
    }

    public function test_release_relays_signed_payload(): void
    {
        $trade = $this->takeOfferAsBuyer();

        $mock = Mockery::mock(BlockchainService::class)->makePartial();
        $mock->shouldReceive('executeMetaSellRelease')
            ->once()
            ->andReturn('0x' . str_repeat('a', 64));
        $this->app->instance(BlockchainService::class, $mock);

        Sanctum::actingAs($this->sellerUser);
        $this->postJson("/api/trade/{$trade->trade_hash}/sell/release", [
            'signature' => '0x' . str_repeat('b', 130),
            'nonce' => 7,
            'deadline' => time() + 600,
        ])->assertOk()
          ->assertJsonPath('data.release_tx_hash', '0x' . str_repeat('a', 64));
    }

    public function test_release_rejects_non_seller(): void
    {
        $trade = $this->takeOfferAsBuyer();

        Sanctum::actingAs($this->buyerUser);
        $this->postJson("/api/trade/{$trade->trade_hash}/sell/release", [
            'signature' => '0x' . str_repeat('b', 130),
            'nonce' => 0,
            'deadline' => time() + 600,
        ])->assertStatus(422);
    }

    public function test_dispute_can_be_opened_by_either_party(): void
    {
        $trade = $this->takeOfferAsBuyer();

        Sanctum::actingAs($this->buyerUser);
        $this->postJson("/api/trade/{$trade->trade_hash}/sell/dispute")
            ->assertOk()
            ->assertJsonPath('data.status', 'disputed');
    }

    public function test_take_dispatches_trade_initiated_event(): void
    {
        Event::fake([TradeInitiated::class]);
        $this->takeOfferAsBuyer();
        Event::assertDispatched(TradeInitiated::class);
    }

    public function test_mark_paid_dispatches_payment_marked_event(): void
    {
        $trade = $this->takeOfferAsBuyer();
        Event::fake([PaymentMarked::class]);
        Sanctum::actingAs($this->buyerUser);
        $this->postJson("/api/trade/{$trade->trade_hash}/sell/mark-paid")->assertOk();
        Event::assertDispatched(PaymentMarked::class);
    }

    public function test_take_rejects_buyer_without_kyc_when_offer_requires_it(): void
    {
        $this->offer->update(['require_kyc' => true]);
        $this->buyerMerchant->update(['kyc_status' => 'pending']);

        Sanctum::actingAs($this->buyerUser);
        $response = $this->postJson('/api/sell-offer/' . $this->offer->slug . '/take', [
            'merchant_payment_method_id' => $this->sellerPaymentMethod->id,
            'take_tx_hash' => '0x' . str_repeat('e', 64),
        ]);
        $response->assertStatus(422);
        $this->assertStringContainsString('KYC', $response->json('message'));
    }

    public function test_take_allows_buyer_with_approved_kyc_when_required(): void
    {
        $this->offer->update(['require_kyc' => true]);
        $this->buyerMerchant->update(['kyc_status' => 'approved']);

        Sanctum::actingAs($this->buyerUser);
        $this->postJson('/api/sell-offer/' . $this->offer->slug . '/take', [
            'merchant_payment_method_id' => $this->sellerPaymentMethod->id,
            'take_tx_hash' => '0x' . str_repeat('f', 64),
        ])->assertCreated();
    }

    public function test_dispute_rejects_third_party(): void
    {
        $trade = $this->takeOfferAsBuyer();

        $strangerWallet = '0xcccccccccccccccccccccccccccccccccccccccc';
        $strangerUser = User::create([
            'name' => 'Stranger', 'email' => 'x@test.com',
            'password' => Hash::make('password'),
            'wallet_address' => $strangerWallet,
        ]);
        Merchant::create([
            'wallet_address' => $strangerWallet, 'username' => 'stranger',
            'is_active' => true, 'rank_id' => MerchantRank::first()->id,
            'member_since' => now(),
        ]);

        Sanctum::actingAs($strangerUser);
        $this->postJson("/api/trade/{$trade->trade_hash}/sell/dispute")
            ->assertStatus(422);
    }

    public function test_take_snapshots_seller_payment_details_into_trade(): void
    {
        $trade = $this->takeOfferAsBuyer();

        $snapshot = $trade->fresh()->seller_payment_snapshot;
        $this->assertIsArray((array) $snapshot);
        $this->assertEquals($this->sellerPaymentMethod->id, $snapshot['merchant_payment_method_id']);
        $this->assertEquals('BHD Bank', $snapshot['label']);
        $this->assertEquals('1234567890', $snapshot['details']['account_number']);
        $this->assertEquals('Use trade hash as reference', $snapshot['safety_note']);
    }

    public function test_snapshot_persists_after_seller_deletes_original_payment_method(): void
    {
        $trade = $this->takeOfferAsBuyer();
        $this->sellerPaymentMethod->delete();

        $reloaded = $trade->fresh();
        $this->assertNotNull($reloaded->seller_payment_snapshot);
        $this->assertEquals('BHD Bank', $reloaded->seller_payment_snapshot['label']);
        $this->assertEquals('1234567890', $reloaded->seller_payment_snapshot['details']['account_number']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
