<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sell_offers', function (Blueprint $table) {
            $table->string('trade_id', 66)->nullable()->unique()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('sell_offers', function (Blueprint $table) {
            $table->dropUnique(['trade_id']);
            $table->dropColumn('trade_id');
        });
    }
};
