<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Trades — frequently queried by status + expires_at (expiration scheduler)
        Schema::table('trades', function (Blueprint $table) {
            $table->index(['status', 'expires_at']);
            $table->index('trade_hash');
        });

        // Disputes — filter by status
        Schema::table('disputes', function (Blueprint $table) {
            $table->index('status');
        });

        // P2P Notifications — merchant + read status for badge count
        Schema::table('p2p_notifications', function (Blueprint $table) {
            $table->index(['merchant_id', 'is_read', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropIndex(['status', 'expires_at']);
            $table->dropIndex(['trade_hash']);
        });

        Schema::table('disputes', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('p2p_notifications', function (Blueprint $table) {
            $table->dropIndex(['merchant_id', 'is_read', 'created_at']);
        });
    }
};
