<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_trading_links', function (Blueprint $table) {
            // Buy/sell discriminator. Distinct from existing `type` column
            // which holds link visibility (public|private).
            $table->enum('flow_type', ['buy', 'sell'])->default('buy')->after('type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('merchant_trading_links', function (Blueprint $table) {
            $table->dropIndex(['flow_type']);
            $table->dropColumn('flow_type');
        });
    }
};
