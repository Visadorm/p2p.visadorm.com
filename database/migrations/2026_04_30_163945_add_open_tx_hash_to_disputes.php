<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * B8: separate columns for dispute open vs resolve tx hashes.
     * Previously resolution_tx_hash was used for both — opening a dispute
     * wrote the open tx, then resolveDispute overwrote it with the resolve tx,
     * destroying the audit trail.
     */
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->string('open_tx_hash')->nullable()->after('resolution_tx_hash');
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn('open_tx_hash');
        });
    }
};
