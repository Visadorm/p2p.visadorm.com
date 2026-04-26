<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('sell_offers');

        if (Schema::hasTable('trades')) {
            Schema::table('trades', function (Blueprint $table) {
                $existing = Schema::getColumnListing('trades');

                if (in_array('seller_wallet', $existing, true)) {
                    try { $table->dropIndex(['seller_wallet']); } catch (\Throwable) {}
                }
                if (in_array('sell_offer_id', $existing, true)) {
                    try { $table->dropIndex(['sell_offer_id']); } catch (\Throwable) {}
                }

                $cols = array_values(array_intersect([
                    'seller_wallet',
                    'sell_offer_id',
                    'release_signature',
                    'release_signature_nonce',
                    'release_signature_deadline',
                    'seller_payment_snapshot',
                ], $existing));

                if ($cols) {
                    $table->dropColumn($cols);
                }
            });
        }

        DB::table('settings')
            ->where('group', 'trade')
            ->whereIn('name', [
                'sell_enabled',
                'sell_max_offers_per_wallet',
                'sell_max_outstanding_usdc',
                'sell_kyc_threshold_usdc',
                'sell_kyc_threshold_window_days',
                'sell_cash_meeting_enabled',
                'sell_default_offer_timer_minutes',
            ])
            ->delete();

        DB::table('migrations')
            ->whereIn('migration', [
                '2026_04_25_000001_add_sell_columns_to_trades_table',
                '2026_04_25_000002_create_sell_offers_table',
                '2026_04_26_000001_add_seller_payment_snapshot_to_trades_table',
                '2026_04_26_000002_encrypt_merchant_payment_method_details',
                '2026_04_26_000003_drop_stale_reviews_trade_id_unique',
                '2026_04_26_000004_add_trade_id_to_sell_offers',
            ])
            ->delete();
    }

    public function down(): void
    {
    }
};
