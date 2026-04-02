<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // [HIGH] Duplicate trade check — TradeController:160
        // Query: WHERE merchant_id = ? AND buyer_wallet = ? AND status IN (...)
        Schema::table('trades', function (Blueprint $table) {
            $table->index(['merchant_id', 'buyer_wallet', 'status'], 'trades_merchant_buyer_status_idx');
        });

        // [HIGH] Stuck completed trades — RetryBlockchainConfirm:22
        // Query: WHERE status = ? AND completed_at >= ?
        Schema::table('trades', function (Blueprint $table) {
            $table->index(['status', 'completed_at'], 'trades_status_completed_at_idx');
        });

        // [HIGH] Stuck pending trades — RetryBlockchainConfirm:55
        // Also benefits MerchantTradeController::index date range filters
        // Query: WHERE status = ? AND created_at >= ? AND created_at <= ?
        Schema::table('trades', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'trades_status_created_at_idx');
        });

        // [MEDIUM] Reviews filtering — MerchantController:156-163
        // Queries: WHERE merchant_id = ? AND is_hidden = false (count, avg, latest)
        // Covering index includes rating to avoid row lookups for AVG(rating)
        Schema::table('reviews', function (Blueprint $table) {
            $table->index(['merchant_id', 'is_hidden', 'rating'], 'reviews_merchant_hidden_rating_idx');
        });

        // [LOW] KYC status check — TradeController:140
        // Query: WHERE merchant_id = ? AND status = ?
        Schema::table('kyc_documents', function (Blueprint $table) {
            $table->index(['merchant_id', 'status'], 'kyc_merchant_status_idx');
        });

        // [INFO] Drop redundant trade_hash single index — already has UNIQUE constraint
        Schema::table('trades', function (Blueprint $table) {
            $table->dropIndex(['trade_hash']);
        });
    }

    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropIndex('trades_merchant_buyer_status_idx');
            $table->dropIndex('trades_status_completed_at_idx');
            $table->dropIndex('trades_status_created_at_idx');
            // Restore the redundant index on rollback
            $table->index('trade_hash');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_merchant_hidden_rating_idx');
        });

        Schema::table('kyc_documents', function (Blueprint $table) {
            $table->dropIndex('kyc_merchant_status_idx');
        });
    }
};
