<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->string('trade_hash', 66)->unique();
            $table->foreignId('trading_link_id')->nullable()->constrained('merchant_trading_links');
            $table->foreignId('merchant_id')->constrained('merchants');
            $table->string('buyer_wallet', 42);
            $table->decimal('amount_usdc', 20, 6);
            $table->decimal('amount_fiat', 20, 6);
            $table->string('currency_code', 3);
            $table->decimal('exchange_rate', 20, 6);
            $table->decimal('fee_amount', 20, 6);
            $table->string('payment_method');
            $table->string('type');
            $table->string('status')->default('pending');
            $table->decimal('stake_amount', 20, 6)->default(0);
            $table->string('stake_paid_by')->nullable();
            $table->string('escrow_tx_hash')->nullable();
            $table->string('release_tx_hash')->nullable();
            $table->string('bank_proof_path')->nullable();
            $table->string('bank_proof_status')->nullable();
            $table->string('buyer_id_path')->nullable();
            $table->string('buyer_id_status')->nullable();
            $table->string('meeting_location')->nullable();
            $table->decimal('meeting_lat', 10, 7)->nullable();
            $table->decimal('meeting_lng', 10, 7)->nullable();
            $table->string('nft_token_id')->nullable();
            $table->json('nft_metadata')->nullable();
            $table->timestamp('disputed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
            $table->index(['buyer_wallet', 'status']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
