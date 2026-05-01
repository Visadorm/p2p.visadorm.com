<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Merchant;
use App\Models\MerchantRank;
use App\Models\User;
use App\Services\KycService;
use Database\Seeders\MerchantRankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KycProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MerchantRankSeeder::class);

        $rank = MerchantRank::where('slug', 'new-member')->first();
        $wallet = '0x' . str_repeat('a', 40);

        $this->user = User::create([
            'name' => 'KYC User',
            'email' => 'kyc@test.com',
            'password' => Hash::make('password'),
            'wallet_address' => $wallet,
        ]);

        $this->merchant = Merchant::create([
            'wallet_address' => $wallet,
            'username' => 'kyc_user',
            'is_active' => true,
            'rank_id' => $rank->id,
            'member_since' => now(),
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_a08_profile_endpoint_returns_locked_state(): void
    {
        $this->getJson('/api/merchant/kyc/profile')
            ->assertOk()
            ->assertJsonPath('data.is_locked', false)
            ->assertJsonPath('data.kyc_locked_at', null);
    }

    public function test_a08_submit_locks_profile(): void
    {
        $payload = [
            'full_name' => 'Jane Doe',
            'date_of_birth' => '1990-05-15',
            'country_of_birth' => 'US',
            'country_of_residence' => 'US',
            'full_address' => '123 Main St, San Francisco, CA 94105',
            'business_name' => 'Acme Corp',
            'country_of_incorporation' => 'US',
        ];

        $this->postJson('/api/merchant/kyc/profile', $payload)
            ->assertOk()
            ->assertJsonPath('data.is_locked', true)
            ->assertJsonPath('data.full_name', 'Jane Doe')
            ->assertJsonPath('data.date_of_birth', '1990-05-15');

        $this->assertNotNull($this->merchant->fresh()->kyc_locked_at);
    }

    public function test_a08_resubmit_after_lock_blocked(): void
    {
        $payload = [
            'full_name' => 'Jane Doe',
            'date_of_birth' => '1990-05-15',
            'country_of_birth' => 'US',
            'country_of_residence' => 'US',
            'full_address' => '123 Main St, San Francisco, CA 94105',
        ];

        $this->postJson('/api/merchant/kyc/profile', $payload)->assertOk();

        // Try to amend — must be rejected
        $this->postJson('/api/merchant/kyc/profile', array_merge($payload, ['full_name' => 'Different Name']))
            ->assertStatus(422)
            ->assertJsonFragment(['message' => __('p2p.kyc_locked')]);
    }

    public function test_a08_required_fields_validated(): void
    {
        $this->postJson('/api/merchant/kyc/profile', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'full_name',
                'date_of_birth',
                'country_of_birth',
                'country_of_residence',
                'full_address',
            ]);
    }

    public function test_a08_admin_unlock_clears_lock_and_logs_audit(): void
    {
        $payload = [
            'full_name' => 'Jane Doe',
            'date_of_birth' => '1990-05-15',
            'country_of_birth' => 'US',
            'country_of_residence' => 'US',
            'full_address' => '123 Main St',
        ];
        $this->postJson('/api/merchant/kyc/profile', $payload)->assertOk();
        $this->assertNotNull($this->merchant->fresh()->kyc_locked_at);

        // Direct service call (Filament action equivalent)
        app(KycService::class)->adminUnlockProfile($this->merchant->fresh(), 99);

        $fresh = $this->merchant->fresh();
        $this->assertNull($fresh->kyc_locked_at);
        $this->assertEquals(99, $fresh->kyc_unlocked_by);
        $this->assertNotNull($fresh->kyc_unlocked_at);
    }

    public function test_a08_resubmit_succeeds_after_admin_unlock(): void
    {
        $payload = [
            'full_name' => 'Jane Doe',
            'date_of_birth' => '1990-05-15',
            'country_of_birth' => 'US',
            'country_of_residence' => 'US',
            'full_address' => '123 Main St',
        ];
        $this->postJson('/api/merchant/kyc/profile', $payload)->assertOk();

        app(KycService::class)->adminUnlockProfile($this->merchant->fresh(), 99);

        $this->postJson('/api/merchant/kyc/profile', array_merge($payload, ['full_name' => 'Updated Name']))
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Updated Name')
            ->assertJsonPath('data.is_locked', true);
    }
}
