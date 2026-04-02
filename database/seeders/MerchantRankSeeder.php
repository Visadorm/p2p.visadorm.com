<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MerchantRankSeeder extends Seeder
{
    public function run(): void
    {
        $ranks = [
            [
                'name' => 'New Member',
                'slug' => 'new-member',
                'min_trades' => 0,
                'min_completion_rate' => null,
                'min_volume' => null,
                'badge_icon' => null,
                'sort_order' => 1,
            ],
            [
                'name' => 'Junior Member',
                'slug' => 'junior-member',
                'min_trades' => 21,
                'min_completion_rate' => null,
                'min_volume' => null,
                'badge_icon' => null,
                'sort_order' => 2,
            ],
            [
                'name' => 'Senior Member',
                'slug' => 'senior-member',
                'min_trades' => 101,
                'min_completion_rate' => 90.00,
                'min_volume' => null,
                'badge_icon' => null,
                'sort_order' => 3,
            ],
            [
                'name' => 'Hero Merchant',
                'slug' => 'hero-merchant',
                'min_trades' => 1000,
                'min_completion_rate' => 95.00,
                'min_volume' => 1000000.000000,
                'badge_icon' => null,
                'sort_order' => 4,
            ],
            [
                'name' => 'Elite Merchant',
                'slug' => 'elite-merchant',
                'min_trades' => 10000,
                'min_completion_rate' => 97.00,
                'min_volume' => 10000000.000000,
                'badge_icon' => null,
                'sort_order' => 5,
            ],
            [
                'name' => 'Legendary Merchant',
                'slug' => 'legendary-merchant',
                'min_trades' => 999999, // Admin-assigned only — unreachable by normal progression
                'min_completion_rate' => null,
                'min_volume' => null,
                'badge_icon' => null,
                'sort_order' => 6,
            ],
        ];

        foreach ($ranks as $rank) {
            DB::table('merchant_ranks')
                ->updateOrInsert(
                    ['slug' => $rank['slug']],
                    $rank,
                );
        }
    }
}
