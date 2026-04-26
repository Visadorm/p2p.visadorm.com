<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Merchant;
use App\Models\MerchantPaymentMethod;
use App\Models\MerchantRank;
use App\Models\SellOffer;
use App\Models\User;
use App\Settings\TradeSettings;
use Database\Seeders\MerchantRankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellOfferControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $sellerUser;
    private Merchant $sellerMerchant;
    private MerchantPaymentMethod $sellerPaymentMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MerchantRankSeeder::class);
        $rank = MerchantRank::where('slug', 'junior-member')->first();

        $wallet = '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $this->sellerUser = User::create([
            'name' => 'Seller One',
            'email' => 'seller@test.com',
            'password' => Hash::make('password'),
            'wallet_address' => $wallet,
        ]);
        $this->sellerMerchant = Merchant::create([
            'wallet_address' => $wallet,
            'username' => 'seller_one',
            'email' => 'seller@test.com',
            'bio' => 'sell',
            'is_active' => true,
            'rank_id' => $rank->id,
            'kyc_status' => 'approved',
            'member_since' => now(),
        ]);

        app(TradeSettings::class)->fill([
            'sell_enabled' => true,
            'sell_max_offers_per_wallet' => 3,
            'sell_max_outstanding_usdc' => 1000,
            'sell_kyc_threshold_usdc' => 100000,
            'sell_kyc_threshold_window_days' => 30,
            'sell_cash_meeting_enabled' => false,
            'sell_default_offer_timer_minutes' => 60,
        ])->save();

        $this->sellerPaymentMethod = MerchantPaymentMethod::create([
            'merchant_id' => $this->sellerMerchant->id,
            'type' => 'bank_transfer',
            'provider' => 'bank',
            'label' => 'Test BHD Bank',
            'details' => ['account_number' => '1234567890', 'beneficiary' => 'Seller One'],
            'is_active' => true,
        ]);
    }

    private function authedAsSeller(): self
    {
        Sanctum::actingAs($this->sellerUser);
        return $this;
    }

    private function validOfferPayload(array $overrides = []): array
    {
        return array_merge([
            'amount_usdc' => 200,
            'currency_code' => 'DOP',
            'fiat_rate' => 62.5,
            'payment_methods' => [
                ['merchant_payment_method_id' => $this->sellerPaymentMethod->id],
            ],
            'trade_id' => '0x' . bin2hex(random_bytes(32)),
            'fund_tx_hash' => '0x' . bin2hex(random_bytes(32)),
        ], $overrides);
    }

    public function test_public_index_lists_active_offers(): void
    {
        SellOffer::create([
            'slug' => 'abcdef123456',
            'seller_wallet' => $this->sellerMerchant->wallet_address,
            'seller_merchant_id' => $this->sellerMerchant->id,
            'amount_usdc' => 100, 'amount_remaining_usdc' => 100,
            'min_trade_usdc' => 10, 'max_trade_usdc' => 100,
            'currency_code' => 'DOP', 'fiat_rate' => 62,
            'payment_methods' => [['type' => 'bank', 'label' => 'BHD']],
            'is_active' => true, 'is_private' => false,
        ]);

        $this->getJson('/api/sell-offers')
            ->assertOk()
            ->assertJsonPath('data.offers.0.slug', 'abcdef123456');
    }

    public function test_public_show_returns_offer(): void
    {
        SellOffer::create([
            'slug' => 'showslug1234',
            'seller_wallet' => $this->sellerMerchant->wallet_address,
            'seller_merchant_id' => $this->sellerMerchant->id,
            'amount_usdc' => 50, 'amount_remaining_usdc' => 50,
            'min_trade_usdc' => 10, 'max_trade_usdc' => 50,
            'currency_code' => 'DOP', 'fiat_rate' => 62,
            'payment_methods' => [['type' => 'bank', 'label' => 'BHD']],
            'is_active' => true, 'is_private' => false,
        ]);

        $this->getJson('/api/sell-offer/showslug1234')
            ->assertOk()
            ->assertJsonPath('data.slug', 'showslug1234');
    }

    public function test_public_show_returns_404_when_missing(): void
    {
        $this->getJson('/api/sell-offer/notfound9999')->assertNotFound();
    }

    public function test_store_requires_auth(): void
    {
        $this->postJson('/api/sell-offers', $this->validOfferPayload())->assertUnauthorized();
    }

    public function test_store_creates_offer_and_returns_it(): void
    {
        $this->authedAsSeller()
            ->postJson('/api/sell-offers', $this->validOfferPayload())
            ->assertCreated()
            ->assertJsonPath('data.amount_usdc', '200.000000')
            ->assertJsonPath('data.currency_code', 'DOP');

        $this->assertDatabaseCount('sell_offers', 1);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->authedAsSeller()
            ->postJson('/api/sell-offers', [])
            ->assertStatus(422);
    }

    public function test_store_rejects_when_sell_disabled(): void
    {
        app(TradeSettings::class)->fill(['sell_enabled' => false])->save();
        $this->authedAsSeller()
            ->postJson('/api/sell-offers', $this->validOfferPayload())
            ->assertStatus(422);
    }

    public function test_store_enforces_max_offers_per_wallet(): void
    {
        for ($i = 0; $i < 3; $i++) {
            SellOffer::create([
                'slug' => 'slug' . str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'seller_wallet' => $this->sellerMerchant->wallet_address,
                'seller_merchant_id' => $this->sellerMerchant->id,
                'amount_usdc' => 100, 'amount_remaining_usdc' => 100,
                'min_trade_usdc' => 10, 'max_trade_usdc' => 100,
                'currency_code' => 'DOP', 'fiat_rate' => 62,
                'payment_methods' => [['type' => 'bank', 'label' => 'BHD']],
                'is_active' => true, 'is_private' => false,
            ]);
        }

        $response = $this->authedAsSeller()
            ->postJson('/api/sell-offers', $this->validOfferPayload());

        $response->assertStatus(422);
        $this->assertStringContainsString('active sell offers', $response->json('message'));
    }

    public function test_store_enforces_outstanding_cap(): void
    {
        SellOffer::create([
            'slug' => 'capslug12345',
            'seller_wallet' => $this->sellerMerchant->wallet_address,
            'seller_merchant_id' => $this->sellerMerchant->id,
            'amount_usdc' => 900, 'amount_remaining_usdc' => 900,
            'min_trade_usdc' => 10, 'max_trade_usdc' => 900,
            'currency_code' => 'DOP', 'fiat_rate' => 62,
            'payment_methods' => [['type' => 'bank', 'label' => 'BHD']],
            'is_active' => true, 'is_private' => false,
        ]);

        $response = $this->authedAsSeller()
            ->postJson('/api/sell-offers', $this->validOfferPayload(['amount_usdc' => 200]));

        $response->assertStatus(422);
        $this->assertStringContainsString('Outstanding sell USDC', $response->json('message'));
    }

    public function test_destroy_lets_seller_cancel_own_offer(): void
    {
        $offer = SellOffer::create([
            'slug' => 'cancelme1234',
            'seller_wallet' => $this->sellerMerchant->wallet_address,
            'seller_merchant_id' => $this->sellerMerchant->id,
            'amount_usdc' => 100, 'amount_remaining_usdc' => 100,
            'min_trade_usdc' => 10, 'max_trade_usdc' => 100,
            'currency_code' => 'DOP', 'fiat_rate' => 62,
            'payment_methods' => [['type' => 'bank', 'label' => 'BHD']],
            'is_active' => true, 'is_private' => false,
        ]);

        $this->authedAsSeller()
            ->deleteJson('/api/sell-offers/' . $offer->slug)
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_destroy_blocks_other_wallets(): void
    {
        $offer = SellOffer::create([
            'slug' => 'otherslug123',
            'seller_wallet' => '0x0000000000000000000000000000000000000099',
            'amount_usdc' => 100, 'amount_remaining_usdc' => 100,
            'min_trade_usdc' => 10, 'max_trade_usdc' => 100,
            'currency_code' => 'DOP', 'fiat_rate' => 62,
            'payment_methods' => [['type' => 'bank', 'label' => 'BHD']],
            'is_active' => true, 'is_private' => false,
        ]);

        $this->authedAsSeller()
            ->deleteJson('/api/sell-offers/' . $offer->slug)
            ->assertStatus(422);
    }

    public function test_mine_lists_only_own_offers(): void
    {
        SellOffer::create([
            'slug' => 'myslug012345',
            'seller_wallet' => $this->sellerMerchant->wallet_address,
            'seller_merchant_id' => $this->sellerMerchant->id,
            'amount_usdc' => 100, 'amount_remaining_usdc' => 100,
            'min_trade_usdc' => 10, 'max_trade_usdc' => 100,
            'currency_code' => 'DOP', 'fiat_rate' => 62,
            'payment_methods' => [['type' => 'bank', 'label' => 'BHD']],
            'is_active' => true, 'is_private' => false,
        ]);
        SellOffer::create([
            'slug' => 'otherslg9999',
            'seller_wallet' => '0x0000000000000000000000000000000000000099',
            'amount_usdc' => 100, 'amount_remaining_usdc' => 100,
            'min_trade_usdc' => 10, 'max_trade_usdc' => 100,
            'currency_code' => 'DOP', 'fiat_rate' => 62,
            'payment_methods' => [['type' => 'bank', 'label' => 'BHD']],
            'is_active' => true, 'is_private' => false,
        ]);

        $response = $this->authedAsSeller()->getJson('/api/sell-offers/mine');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('myslug012345', $response->json('data.0.slug'));
    }

    public function test_store_rejects_payment_method_owned_by_someone_else(): void
    {
        $otherWallet = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $rank = MerchantRank::where('slug', 'junior-member')->first();
        $other = Merchant::create([
            'wallet_address' => $otherWallet,
            'username' => 'other_one',
            'email' => 'other@test.com',
            'bio' => 's', 'is_active' => true,
            'rank_id' => $rank->id, 'kyc_status' => 'approved',
            'member_since' => now(),
        ]);
        $stranger = MerchantPaymentMethod::create([
            'merchant_id' => $other->id,
            'type' => 'bank_transfer', 'provider' => 'bank',
            'label' => 'Other Bank', 'details' => ['x' => 'y'],
            'is_active' => true,
        ]);

        $this->authedAsSeller()
            ->postJson('/api/sell-offers', $this->validOfferPayload([
                'payment_methods' => [['merchant_payment_method_id' => $stranger->id]],
            ]))
            ->assertStatus(422);

        $this->assertDatabaseCount('sell_offers', 0);
    }

    public function test_store_rejects_inactive_payment_method(): void
    {
        $this->sellerPaymentMethod->update(['is_active' => false]);

        $this->authedAsSeller()
            ->postJson('/api/sell-offers', $this->validOfferPayload())
            ->assertStatus(422);
    }
}
