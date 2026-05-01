<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A5: private buyer ↔ seller chat scoped to a single trade.
     * Auto-locked once the trade is Completed/Cancelled (enforced in service).
     */
    public function up(): void
    {
        Schema::create('trade_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_id')->constrained('trades')->cascadeOnDelete();
            $table->string('sender_wallet'); // lowercase 0x... wallet
            $table->enum('sender_role', ['seller', 'buyer']);
            $table->text('body')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamps();

            $table->index(['trade_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_messages');
    }
};
