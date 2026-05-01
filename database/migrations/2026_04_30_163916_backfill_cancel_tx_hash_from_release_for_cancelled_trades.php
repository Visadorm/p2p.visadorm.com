<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * B5 backfill: legacy cancel jobs wrote tx hashes to release_tx_hash.
     * For trades that ended up Cancelled, copy that hash to cancel_tx_hash
     * (only if cancel_tx_hash is still null) and clear release_tx_hash so
     * downstream checks that interpret release_tx_hash as "released to merchant"
     * stop misreading cancelled trades.
     */
    public function up(): void
    {
        DB::table('trades')
            ->where('status', 'cancelled')
            ->whereNotNull('release_tx_hash')
            ->whereNull('cancel_tx_hash')
            ->update([
                'cancel_tx_hash' => DB::raw('release_tx_hash'),
                'release_tx_hash' => null,
            ]);
    }

    public function down(): void
    {
        // Reverse: move it back. Idempotent best-effort.
        DB::table('trades')
            ->where('status', 'cancelled')
            ->whereNotNull('cancel_tx_hash')
            ->whereNull('release_tx_hash')
            ->update([
                'release_tx_hash' => DB::raw('cancel_tx_hash'),
                'cancel_tx_hash' => null,
            ]);
    }
};
