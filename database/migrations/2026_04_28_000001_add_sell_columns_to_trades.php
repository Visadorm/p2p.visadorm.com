<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->string('seller_wallet', 42)->nullable()->after('merchant_id')->index();
            $table->string('fund_tx_hash', 66)->nullable()->after('release_tx_hash');
            $table->string('join_tx_hash', 66)->nullable()->after('fund_tx_hash');
            $table->string('mark_paid_tx_hash', 66)->nullable()->after('join_tx_hash');
            $table->string('cancel_tx_hash', 66)->nullable()->after('mark_paid_tx_hash');
            $table->string('dispute_tx_hash', 66)->nullable()->after('cancel_tx_hash');
            $table->boolean('is_cash_trade')->default(false)->after('payment_method');
            $table->string('cash_proof_url')->nullable()->after('is_cash_trade');
            $table->boolean('seller_verified_payment')->default(false)->after('cash_proof_url');
        });
    }

    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropIndex(['seller_wallet']);
            $table->dropColumn([
                'seller_wallet',
                'fund_tx_hash',
                'join_tx_hash',
                'mark_paid_tx_hash',
                'cancel_tx_hash',
                'dispute_tx_hash',
                'is_cash_trade',
                'cash_proof_url',
                'seller_verified_payment',
            ]);
        });
    }
};
