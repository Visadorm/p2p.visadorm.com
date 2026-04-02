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
        Schema::create('merchant_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants');
            $table->date('date');
            $table->integer('trades_count')->default(0);
            $table->decimal('volume', 20, 6)->default(0);
            $table->integer('completed_count')->default(0);
            $table->integer('disputed_count')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->unique(['merchant_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_stats');
    }
};
