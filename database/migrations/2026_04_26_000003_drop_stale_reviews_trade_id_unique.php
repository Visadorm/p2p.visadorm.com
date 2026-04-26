<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $exists = collect(DB::select('SHOW INDEX FROM reviews'))
                ->contains(fn ($i) => $i->Key_name === 'reviews_trade_id_unique');
            if ($exists) {
                DB::statement('ALTER TABLE reviews DROP INDEX reviews_trade_id_unique');
            }
        } else {
            try {
                Schema::table('reviews', function ($table) {
                    $table->dropUnique('reviews_trade_id_unique');
                });
            } catch (\Throwable) {
                // Index doesn't exist on this DB — nothing to drop
            }
        }
    }

    public function down(): void
    {
        // No safe restore: re-adding the unique would conflict with existing
        // multi-role review rows. Intentional one-way migration.
    }
};
