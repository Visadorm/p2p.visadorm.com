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
            $table->string('release_signature', 200)->nullable()->after('release_tx_hash');
            $table->unsignedBigInteger('release_signature_nonce')->nullable()->after('release_signature');
            $table->timestamp('release_signature_deadline')->nullable()->after('release_signature_nonce');
            $table->foreignId('sell_offer_id')->nullable()->after('trading_link_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropIndex(['seller_wallet']);
            $table->dropIndex(['sell_offer_id']);
            $table->dropColumn([
                'seller_wallet',
                'release_signature',
                'release_signature_nonce',
                'release_signature_deadline',
                'sell_offer_id',
            ]);
        });
    }
};
