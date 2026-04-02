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
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('wallet_address', 42)->unique();
            $table->string('username')->nullable()->unique();
            $table->string('email')->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar')->nullable();
            $table->foreignId('rank_id')->constrained('merchant_ranks');
            $table->boolean('is_legendary')->default(false);
            $table->string('kyc_status')->default('pending');
            $table->boolean('bank_verified')->default(false);
            $table->boolean('email_verified')->default(false);
            $table->boolean('business_verified')->default(false);
            $table->boolean('is_fast_responder')->default(false);
            $table->boolean('has_liquidity')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->integer('avg_response_minutes')->nullable();
            $table->integer('total_trades')->default(0);
            $table->decimal('total_volume', 20, 6)->default(0);
            $table->decimal('completion_rate', 5, 2)->default(0);
            $table->decimal('reliability_score', 3, 1)->default(0);
            $table->decimal('dispute_rate', 5, 2)->default(0);
            $table->string('buyer_verification')->default('optional');
            $table->text('trade_instructions')->nullable();
            $table->integer('trade_timer_minutes')->default(30);
            $table->boolean('notify_bank_proof')->default(true);
            $table->boolean('notify_buyer_id')->default(true);
            $table->boolean('notify_email')->default(true);
            $table->boolean('notify_sms')->default(false);
            $table->timestamp('member_since');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
