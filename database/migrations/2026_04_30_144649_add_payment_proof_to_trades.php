<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A4: buyer uploads proof of fiat payment so seller can verify before
     * release. Reduces disputes. Image/PDF on the private disk.
     */
    public function up(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->string('payment_proof_url')->nullable()->after('cash_proof_url');
            $table->timestamp('payment_proof_uploaded_at')->nullable()->after('payment_proof_url');
        });
    }

    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropColumn(['payment_proof_url', 'payment_proof_uploaded_at']);
        });
    }
};
