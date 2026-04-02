<?php

namespace Tests\Feature\Api;

use App\Contracts\ExchangeRateProvider;
use App\Models\Merchant;
use App\Models\MerchantCurrency;
use App\Models\MerchantRank;
use App\Models\User;
use App\Services\ExchangeRates\ArrayExchangeRateProvider;
use Database\Seeders\MerchantRankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MerchantCurrencyControllerTest extends TestCase
{
    use RefreshDatabase;

    private Merchant $merchant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Use fixed rates in tests — never hit the real CoinGecko API
        $this->app->bind(ExchangeRateProvider::class, fn () => new ArrayExchangeRateProvider([
            'DOP' => 57.0,
            'EUR' => 0.92,
            'NGN' => 1600.0,
            'USD' => 1.0,
        ]));

        $this->seed(MerchantRankSeeder::class);

        $walletAddress = '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $rank = MerchantRank::where('slug', 'new-member')->first();

        $this->user = User::create([
            'name' => 'Test Merchant',
            'email' => 'merchant@test.com',
            'password' => Hash::make('password'),
            'wallet_address' => $walletAddress,
        ]);

        $this->merchant = Merchant::create([
            'wallet_address' => $walletAddress,
            'username' => 'test_merchant',
            'is_active' => true,
            'rank_id' => $rank->id,
            'total_trades' => 0,
            'total_volume' => 0,
            'completion_rate' => 0,
            'member_since' => now(),
        ]);
    }

    /* -----------------------------------------------------------------
     |  Index
     | ----------------------------------------------------------------- */

    public function test_index_returns_empty_when_no_currencies(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/merchant/currencies');

        $response->assertOk()
            ->assertJsonStructure(['data', 'message'])
            ->assertJsonCount(0, 'data');
    }

    public function test_index_returns_currencies_with_market_rate(): void
    {
        Sanctum::actingAs($this->user);

        MerchantCurrency::create([
            'merchant_id' => $this->merchant->id,
            'currency_code' => 'DOP',
            'markup_percent' => 2.5,
            'min_amount' => 10,
            'max_amount' => 5000,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/merchant/currencies');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.currency_code', 'DOP')
            ->assertJsonPath('data.0.markup_percent', '2.50')
            ->assertJsonPath('data.0.market_rate', 57); // DOP hardcoded rate in ExchangeRateService
    }

    public function test_index_attaches_zero_market_rate_for_unknown_currency(): void
    {
        Sanctum::actingAs($this->user);

        MerchantCurrency::create([
            'merchant_id' => $this->merchant->id,
            'currency_code' => 'XYZ',
            'markup_percent' => 1.0,
            'min_amount' => 5,
            'max_amount' => 1000,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/merchant/currencies');

        $response->assertOk()
            ->assertJsonPath('data.0.currency_code', 'XYZ')
            ->assertJsonPath('data.0.market_rate', 0);
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/merchant/currencies');

        $response->assertStatus(401);
    }

    /* -----------------------------------------------------------------
     |  Store
     | ----------------------------------------------------------------- */

    public function test_store_creates_a_currency(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/merchant/currencies', [
            'currency_code' => 'DOP',
            'markup_percent' => 3.0,
            'min_amount' => 50,
            'max_amount' => 10000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.currency_code', 'DOP')
            ->assertJsonPath('data.markup_percent', '3.00');

        $this->assertDatabaseHas('merchant_currencies', [
            'merchant_id' => $this->merchant->id,
            'currency_code' => 'DOP',
        ]);
    }

    public function test_store_requires_currency_code(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/merchant/currencies', [
            'markup_percent' => 2.0,
            'min_amount' => 10,
            'max_amount' => 5000,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['currency_code']);
    }

    public function test_store_rejects_markup_above_100(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/merchant/currencies', [
            'currency_code' => 'DOP',
            'markup_percent' => 101,
            'min_amount' => 10,
            'max_amount' => 5000,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['markup_percent']);
    }

    public function test_store_rejects_max_amount_not_greater_than_min(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/merchant/currencies', [
            'currency_code' => 'DOP',
            'markup_percent' => 2.0,
            'min_amount' => 5000,
            'max_amount' => 500,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['max_amount']);
    }

    /* -----------------------------------------------------------------
     |  Update
     | ----------------------------------------------------------------- */

    public function test_update_modifies_a_currency(): void
    {
        Sanctum::actingAs($this->user);

        $currency = MerchantCurrency::create([
            'merchant_id' => $this->merchant->id,
            'currency_code' => 'DOP',
            'markup_percent' => 2.0,
            'min_amount' => 10,
            'max_amount' => 5000,
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/merchant/currencies/{$currency->id}", [
            'markup_percent' => 5.0,
            'min_amount' => 20,
            'max_amount' => 8000,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.markup_percent', '5.00');

        $this->assertDatabaseHas('merchant_currencies', [
            'id' => $currency->id,
            'min_amount' => 20,
            'max_amount' => 8000,
        ]);
    }

    public function test_update_rejects_another_merchants_currency(): void
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::create([
            'name' => 'Other Merchant',
            'email' => 'other@test.com',
            'password' => Hash::make('password'),
            'wallet_address' => '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        ]);

        $rank = MerchantRank::where('slug', 'new-member')->first();
        $otherMerchant = Merchant::create([
            'wallet_address' => '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            'username' => 'other_merchant',
            'is_active' => true,
            'rank_id' => $rank->id,
            'total_trades' => 0,
            'total_volume' => 0,
            'completion_rate' => 0,
            'member_since' => now(),
        ]);

        $otherCurrency = MerchantCurrency::create([
            'merchant_id' => $otherMerchant->id,
            'currency_code' => 'EUR',
            'markup_percent' => 1.0,
            'min_amount' => 5,
            'max_amount' => 1000,
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/merchant/currencies/{$otherCurrency->id}", [
            'markup_percent' => 99.0,
        ]);

        $response->assertForbidden();
    }

    /* -----------------------------------------------------------------
     |  Destroy
     | ----------------------------------------------------------------- */

    public function test_destroy_deletes_a_currency(): void
    {
        Sanctum::actingAs($this->user);

        $currency = MerchantCurrency::create([
            'merchant_id' => $this->merchant->id,
            'currency_code' => 'HTG',
            'markup_percent' => 1.5,
            'min_amount' => 100,
            'max_amount' => 50000,
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/merchant/currencies/{$currency->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('merchant_currencies', ['id' => $currency->id]);
    }

    public function test_destroy_rejects_another_merchants_currency(): void
    {
        Sanctum::actingAs($this->user);

        $rank = MerchantRank::where('slug', 'new-member')->first();
        $otherMerchant = Merchant::create([
            'wallet_address' => '0xcccccccccccccccccccccccccccccccccccccccc',
            'username' => 'other_merchant2',
            'is_active' => true,
            'rank_id' => $rank->id,
            'total_trades' => 0,
            'total_volume' => 0,
            'completion_rate' => 0,
            'member_since' => now(),
        ]);

        $otherCurrency = MerchantCurrency::create([
            'merchant_id' => $otherMerchant->id,
            'currency_code' => 'COP',
            'markup_percent' => 2.0,
            'min_amount' => 50,
            'max_amount' => 5000,
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/merchant/currencies/{$otherCurrency->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('merchant_currencies', ['id' => $otherCurrency->id]);
    }
}
