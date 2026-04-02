<?php

namespace Tests\Feature\Api;

use App\Models\Merchant;
use App\Models\MerchantRank;
use App\Models\User;
use App\Services\WalletAuthService;
use Database\Seeders\MerchantRankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $validWallet = '0x1234567890abcdef1234567890abcdef12345678';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MerchantRankSeeder::class);
    }

    /**
     * Create a user with all required fields for SQLite NOT NULL constraints.
     */
    private function createUser(string $walletAddress, string $name = 'Test User'): User
    {
        return User::create([
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'wallet_address' => $walletAddress,
        ]);
    }

    /**
     * Mock the WalletAuthService to bypass actual signature verification.
     */
    private function mockWalletAuth(string $nonce, bool $signatureValid = true): void
    {
        $walletAuthService = Mockery::mock(WalletAuthService::class);
        $walletAuthService->shouldReceive('buildSignMessage')
            ->with($nonce)
            ->andReturn("Sign the nonce to login.\nNonce: {$nonce}");
        $walletAuthService->shouldReceive('verifySignature')
            ->andReturn($signatureValid);

        $this->app->instance(WalletAuthService::class, $walletAuthService);
    }

    /* -----------------------------------------------------------------
     |  Nonce endpoint
     | ----------------------------------------------------------------- */

    public function test_nonce_returns_nonce_and_message(): void
    {
        $response = $this->postJson('/api/auth/nonce', [
            'wallet_address' => $this->validWallet,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['nonce', 'message'],
                'message',
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data['nonce']);
        $this->assertStringContainsString($data['nonce'], $data['message']);
    }

    public function test_nonce_caches_nonce_for_wallet(): void
    {
        $this->postJson('/api/auth/nonce', [
            'wallet_address' => $this->validWallet,
        ]);

        $cached = Cache::get('wallet_nonce:' . strtolower($this->validWallet));

        $this->assertNotNull($cached);
    }

    public function test_nonce_validates_wallet_address_format(): void
    {
        // Missing 0x prefix
        $this->postJson('/api/auth/nonce', [
            'wallet_address' => '1234567890abcdef1234567890abcdef12345678',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('wallet_address');

        // Too short
        $this->postJson('/api/auth/nonce', [
            'wallet_address' => '0x1234',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('wallet_address');

        // Empty
        $this->postJson('/api/auth/nonce', [
            'wallet_address' => '',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('wallet_address');

        // Invalid characters
        $this->postJson('/api/auth/nonce', [
            'wallet_address' => '0xZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZ',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('wallet_address');
    }

    public function test_nonce_requires_wallet_address(): void
    {
        $this->postJson('/api/auth/nonce', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('wallet_address');
    }

    /* -----------------------------------------------------------------
     |  Verify endpoint
     | ----------------------------------------------------------------- */

    public function test_verify_creates_merchant_and_returns_token_for_existing_user(): void
    {
        $walletAddress = strtolower($this->validWallet);
        $nonce = 'test-nonce-12345';

        // Pre-create user (controller's firstOrCreate will find it)
        $this->createUser($walletAddress);

        Cache::put('wallet_nonce:' . $walletAddress, $nonce, 300);
        $this->mockWalletAuth($nonce);

        $response = $this->postJson('/api/auth/verify', [
            'wallet_address' => $this->validWallet,
            'signature' => '0x' . str_repeat('ab', 65),
            'nonce' => $nonce,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['token', 'merchant'],
                'message',
            ]);

        // Merchant was created
        $this->assertDatabaseHas('merchants', [
            'wallet_address' => $walletAddress,
            'is_active' => true,
        ]);

        // Token is present and non-empty
        $token = $response->json('data.token');
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    public function test_verify_returns_token(): void
    {
        $walletAddress = strtolower($this->validWallet);
        $nonce = 'test-nonce-token';

        $this->createUser($walletAddress);

        Cache::put('wallet_nonce:' . $walletAddress, $nonce, 300);
        $this->mockWalletAuth($nonce);

        $response = $this->postJson('/api/auth/verify', [
            'wallet_address' => $this->validWallet,
            'signature' => '0x' . str_repeat('ab', 65),
            'nonce' => $nonce,
        ]);

        $response->assertOk();

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    public function test_verify_clears_nonce_from_cache_after_success(): void
    {
        $walletAddress = strtolower($this->validWallet);
        $nonce = 'test-nonce-cache';

        $this->createUser($walletAddress);

        Cache::put('wallet_nonce:' . $walletAddress, $nonce, 300);
        $this->mockWalletAuth($nonce);

        $this->postJson('/api/auth/verify', [
            'wallet_address' => $this->validWallet,
            'signature' => '0x' . str_repeat('ab', 65),
            'nonce' => $nonce,
        ])->assertOk();

        $this->assertNull(Cache::get('wallet_nonce:' . $walletAddress));
    }

    public function test_verify_fails_with_invalid_nonce(): void
    {
        $walletAddress = $this->validWallet;

        Cache::put('wallet_nonce:' . strtolower($walletAddress), 'correct-nonce', 300);

        $response = $this->postJson('/api/auth/verify', [
            'wallet_address' => $walletAddress,
            'signature' => '0x' . str_repeat('ab', 65),
            'nonce' => 'wrong-nonce',
        ]);

        $response->assertUnprocessable();
    }

    public function test_verify_fails_with_expired_nonce(): void
    {
        $walletAddress = $this->validWallet;

        // No cached nonce
        $response = $this->postJson('/api/auth/verify', [
            'wallet_address' => $walletAddress,
            'signature' => '0x' . str_repeat('ab', 65),
            'nonce' => 'some-nonce',
        ]);

        $response->assertUnprocessable();
    }

    public function test_verify_fails_with_invalid_signature(): void
    {
        $walletAddress = strtolower($this->validWallet);
        $nonce = 'test-nonce-sig';

        Cache::put('wallet_nonce:' . $walletAddress, $nonce, 300);
        $this->mockWalletAuth($nonce, signatureValid: false);

        $response = $this->postJson('/api/auth/verify', [
            'wallet_address' => $this->validWallet,
            'signature' => '0x' . str_repeat('00', 65),
            'nonce' => $nonce,
        ]);

        $response->assertUnprocessable();
    }

    public function test_verify_reuses_existing_user_and_merchant(): void
    {
        $walletAddress = strtolower($this->validWallet);

        $this->createUser($walletAddress, 'Existing');

        $rank = MerchantRank::where('slug', 'new-member')->first();
        Merchant::create([
            'wallet_address' => $walletAddress,
            'username' => 'existing_user',
            'is_active' => true,
            'rank_id' => $rank->id,
            'member_since' => now(),
        ]);

        $nonce = 'test-nonce-existing';
        Cache::put('wallet_nonce:' . $walletAddress, $nonce, 300);
        $this->mockWalletAuth($nonce);

        $response = $this->postJson('/api/auth/verify', [
            'wallet_address' => $walletAddress,
            'signature' => '0x' . str_repeat('ab', 65),
            'nonce' => $nonce,
        ]);

        $response->assertOk();

        // Should not create duplicate records
        $this->assertSame(1, User::where('wallet_address', $walletAddress)->count());
        $this->assertSame(1, Merchant::where('wallet_address', $walletAddress)->count());
    }

    /* -----------------------------------------------------------------
     |  Logout endpoint
     | ----------------------------------------------------------------- */

    public function test_logout_revokes_token(): void
    {
        $walletAddress = strtolower($this->validWallet);
        $user = $this->createUser($walletAddress);

        $rank = MerchantRank::where('slug', 'new-member')->first();
        Merchant::create([
            'wallet_address' => $walletAddress,
            'username' => 'logout_user',
            'is_active' => true,
            'rank_id' => $rank->id,
            'member_since' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/logout');

        $response->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/auth/logout')
            ->assertUnauthorized();
    }

    /* -----------------------------------------------------------------
     |  Me endpoint
     | ----------------------------------------------------------------- */

    public function test_me_returns_merchant_data(): void
    {
        $walletAddress = strtolower($this->validWallet);

        $user = $this->createUser($walletAddress);

        $rank = MerchantRank::where('slug', 'new-member')->first();
        Merchant::create([
            'wallet_address' => $walletAddress,
            'username' => 'test_user',
            'is_active' => true,
            'rank_id' => $rank->id,
            'member_since' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'wallet_address',
                    'username',
                    'rank',
                ],
                'message',
            ]);

        $this->assertSame($walletAddress, $response->json('data.wallet_address'));
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized();
    }
}
