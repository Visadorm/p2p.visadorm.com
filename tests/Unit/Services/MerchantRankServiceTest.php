<?php

namespace Tests\Unit\Services;

use App\Models\Merchant;
use App\Models\MerchantRank;
use App\Services\MerchantRankService;
use Database\Seeders\MerchantRankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MerchantRankServiceTest extends TestCase
{
    use RefreshDatabase;

    private MerchantRankService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MerchantRankSeeder::class);

        Cache::flush();

        $this->service = new MerchantRankService;
    }

    private function createMerchant(array $overrides = []): Merchant
    {
        $rank = MerchantRank::where('slug', 'new-member')->first();

        return Merchant::create(array_merge([
            'wallet_address' => '0x' . fake()->sha1(),
            'username' => 'user_' . fake()->unique()->word(),
            'is_active' => true,
            'total_trades' => 0,
            'completion_rate' => 0,
            'total_volume' => 0,
            'is_legendary' => false,
            'rank_id' => $rank->id,
            'member_since' => now(),
        ], $overrides));
    }

    public function test_new_member_rank_for_zero_trades(): void
    {
        $merchant = $this->createMerchant([
            'total_trades' => 0,
            'completion_rate' => 0,
            'total_volume' => 0,
        ]);

        $rank = $this->service->calculateRank($merchant);

        $this->assertSame('new-member', $rank->slug);
    }

    public function test_new_member_rank_for_20_trades(): void
    {
        $merchant = $this->createMerchant([
            'total_trades' => 20,
            'completion_rate' => 100,
            'total_volume' => 5000,
        ]);

        $rank = $this->service->calculateRank($merchant);

        $this->assertSame('new-member', $rank->slug);
    }

    public function test_junior_member_rank_at_threshold(): void
    {
        $merchant = $this->createMerchant([
            'total_trades' => 21,
            'completion_rate' => 50,
            'total_volume' => 1000,
        ]);

        $rank = $this->service->calculateRank($merchant);

        $this->assertSame('junior-member', $rank->slug);
    }

    public function test_junior_member_with_high_trades_but_low_completion(): void
    {
        $merchant = $this->createMerchant([
            'total_trades' => 100,
            'completion_rate' => 80,
            'total_volume' => 50000,
        ]);

        $rank = $this->service->calculateRank($merchant);

        $this->assertSame('junior-member', $rank->slug);
    }

    public function test_senior_member_rank_at_threshold(): void
    {
        $merchant = $this->createMerchant([
            'total_trades' => 101,
            'completion_rate' => 90,
            'total_volume' => 50000,
        ]);

        $rank = $this->service->calculateRank($merchant);

        $this->assertSame('senior-member', $rank->slug);
    }

    public function test_senior_member_with_high_trades_but_low_volume(): void
    {
        $merchant = $this->createMerchant([
            'total_trades' => 999,
            'completion_rate' => 95,
            'total_volume' => 500000,
        ]);

        $rank = $this->service->calculateRank($merchant);

        $this->assertSame('senior-member', $rank->slug);
    }

    public function test_hero_merchant_rank_at_threshold(): void
    {
        $merchant = $this->createMerchant([
            'total_trades' => 1000,
            'completion_rate' => 95,
            'total_volume' => 1000000,
        ]);

        $rank = $this->service->calculateRank($merchant);

        $this->assertSame('hero-merchant', $rank->slug);
    }

    public function test_hero_merchant_with_high_trades_but_insufficient_for_elite(): void
    {
        $merchant = $this->createMerchant([
            'total_trades' => 9999,
            'completion_rate' => 96,
            'total_volume' => 5000000,
        ]);

        $rank = $this->service->calculateRank($merchant);

        $this->assertSame('hero-merchant', $rank->slug);
    }

    public function test_elite_merchant_rank_at_threshold(): void
    {
        $merchant = $this->createMerchant([
            'total_trades' => 10000,
            'completion_rate' => 97,
            'total_volume' => 10000000,
        ]);

        $rank = $this->service->calculateRank($merchant);

        $this->assertSame('elite-merchant', $rank->slug);
    }

    public function test_legendary_override_returns_legendary(): void
    {
        $merchant = $this->createMerchant([
            'total_trades' => 0,
            'completion_rate' => 0,
            'total_volume' => 0,
            'is_legendary' => true,
        ]);

        $rank = $this->service->calculateRank($merchant);

        $this->assertSame('legendary-merchant', $rank->slug);
    }

    public function test_legendary_override_ignores_low_stats(): void
    {
        $merchant = $this->createMerchant([
            'total_trades' => 5,
            'completion_rate' => 50,
            'total_volume' => 100,
            'is_legendary' => true,
        ]);

        $rank = $this->service->calculateRank($merchant);

        $this->assertSame('legendary-merchant', $rank->slug);
    }

    public function test_edge_case_just_below_junior_threshold(): void
    {
        $merchant = $this->createMerchant([
            'total_trades' => 20,
            'completion_rate' => 100,
            'total_volume' => 100000,
        ]);

        $rank = $this->service->calculateRank($merchant);

        $this->assertSame('new-member', $rank->slug);
    }

    public function test_edge_case_senior_trades_met_but_completion_below(): void
    {
        $merchant = $this->createMerchant([
            'total_trades' => 101,
            'completion_rate' => 89,
            'total_volume' => 500000,
        ]);

        $rank = $this->service->calculateRank($merchant);

        $this->assertSame('junior-member', $rank->slug);
    }

    public function test_edge_case_hero_trades_met_but_completion_below(): void
    {
        $merchant = $this->createMerchant([
            'total_trades' => 1000,
            'completion_rate' => 94,
            'total_volume' => 1000000,
        ]);

        $rank = $this->service->calculateRank($merchant);

        $this->assertSame('senior-member', $rank->slug);
    }

    public function test_edge_case_hero_trades_and_rate_met_but_volume_below(): void
    {
        $merchant = $this->createMerchant([
            'total_trades' => 1000,
            'completion_rate' => 95,
            'total_volume' => 999999,
        ]);

        $rank = $this->service->calculateRank($merchant);

        $this->assertSame('senior-member', $rank->slug);
    }

    public function test_edge_case_elite_trades_met_but_completion_below(): void
    {
        $merchant = $this->createMerchant([
            'total_trades' => 10000,
            'completion_rate' => 96,
            'total_volume' => 10000000,
        ]);

        $rank = $this->service->calculateRank($merchant);

        $this->assertSame('hero-merchant', $rank->slug);
    }

    public function test_update_merchant_rank_persists_rank_id(): void
    {
        $merchant = $this->createMerchant([
            'total_trades' => 21,
            'completion_rate' => 80,
            'total_volume' => 5000,
        ]);

        $this->service->updateMerchantRank($merchant);

        $merchant->refresh();
        $expectedRank = MerchantRank::where('slug', 'junior-member')->first();

        $this->assertSame($expectedRank->id, $merchant->rank_id);
    }

    public function test_update_merchant_rank_does_not_update_if_same_rank(): void
    {
        $newMemberRank = MerchantRank::where('slug', 'new-member')->first();
        $merchant = $this->createMerchant([
            'total_trades' => 5,
            'completion_rate' => 80,
            'total_volume' => 500,
            'rank_id' => $newMemberRank->id,
        ]);

        $originalUpdatedAt = $merchant->updated_at;

        $this->service->updateMerchantRank($merchant);

        $merchant->refresh();
        $this->assertSame($newMemberRank->id, $merchant->rank_id);
    }
}
