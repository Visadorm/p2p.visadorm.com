<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed merchant ranks as part of migrations so they're always present
 * on fresh deploys — no manual seeder run needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ranks = [
            ['name' => 'New Member', 'slug' => 'new-member', 'min_trades' => 0, 'min_completion_rate' => null, 'min_volume' => null, 'badge_icon' => null, 'sort_order' => 1],
            ['name' => 'Junior Member', 'slug' => 'junior-member', 'min_trades' => 21, 'min_completion_rate' => null, 'min_volume' => null, 'badge_icon' => null, 'sort_order' => 2],
            ['name' => 'Senior Member', 'slug' => 'senior-member', 'min_trades' => 101, 'min_completion_rate' => 90.00, 'min_volume' => null, 'badge_icon' => null, 'sort_order' => 3],
            ['name' => 'Hero Merchant', 'slug' => 'hero-merchant', 'min_trades' => 1000, 'min_completion_rate' => 95.00, 'min_volume' => 1000000, 'badge_icon' => null, 'sort_order' => 4],
            ['name' => 'Elite Merchant', 'slug' => 'elite-merchant', 'min_trades' => 10000, 'min_completion_rate' => 97.00, 'min_volume' => 10000000, 'badge_icon' => null, 'sort_order' => 5],
            ['name' => 'Legendary Merchant', 'slug' => 'legendary-merchant', 'min_trades' => 999999, 'min_completion_rate' => null, 'min_volume' => null, 'badge_icon' => null, 'sort_order' => 6],
        ];

        foreach ($ranks as $rank) {
            DB::table('merchant_ranks')->updateOrInsert(
                ['slug' => $rank['slug']],
                $rank,
            );
        }
    }

    public function down(): void
    {
        DB::table('merchant_ranks')->whereIn('slug', [
            'new-member', 'junior-member', 'senior-member',
            'hero-merchant', 'elite-merchant', 'legendary-merchant',
        ])->delete();
    }
};
