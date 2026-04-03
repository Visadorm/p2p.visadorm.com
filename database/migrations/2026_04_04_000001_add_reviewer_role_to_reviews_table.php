<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('reviewer_role', 10)->default('buyer')->after('reviewer_wallet');

            // Drop old unique on trade_id, add composite unique
            $table->dropUnique(['trade_id']);
            $table->unique(['trade_id', 'reviewer_role']);
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique(['trade_id', 'reviewer_role']);
            $table->unique('trade_id');
            $table->dropColumn('reviewer_role');
        });
    }
};
