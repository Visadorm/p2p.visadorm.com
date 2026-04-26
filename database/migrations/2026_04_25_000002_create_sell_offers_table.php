<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sell_offers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('seller_wallet', 42)->index();
            $table->foreignId('seller_merchant_id')->nullable()->index();
            $table->decimal('amount_usdc', 20, 6);
            $table->decimal('amount_remaining_usdc', 20, 6);
            $table->decimal('min_trade_usdc', 20, 6);
            $table->decimal('max_trade_usdc', 20, 6);
            $table->string('currency_code', 3)->index();
            $table->decimal('fiat_rate', 20, 6);
            $table->json('payment_methods');
            $table->text('instructions')->nullable();
            $table->boolean('require_kyc')->default(false);
            $table->boolean('is_private')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('fund_tx_hash', 66)->nullable();
            $table->string('cancel_tx_hash', 66)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'currency_code']);
            $table->index('amount_remaining_usdc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sell_offers');
    }
};
