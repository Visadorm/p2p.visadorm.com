<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reviews', 'reviewer_role')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->string('reviewer_role', 10)->default('buyer')->after('reviewer_wallet');
            });
        }

        // Swap unique constraint: trade_id → (trade_id, reviewer_role)
        try {
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropUnique(['trade_id']);
            });
        } catch (\Throwable) {
            // Index may not exist (fresh DB or already dropped)
        }

        try {
            Schema::table('reviews', function (Blueprint $table) {
                $table->unique(['trade_id', 'reviewer_role']);
            });
        } catch (\Throwable) {
            // Composite index may already exist
        }
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique(['trade_id', 'reviewer_role']);
            $table->unique('trade_id');
            $table->dropColumn('reviewer_role');
        });
    }
};
