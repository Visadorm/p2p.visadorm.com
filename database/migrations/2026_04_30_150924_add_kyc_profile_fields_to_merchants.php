<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A8: KYC identity structure. Once submitted, locked — only admin
     * can amend. `kyc_locked_at` is the immutability marker.
     */
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            // Personal
            $table->date('date_of_birth')->nullable()->after('full_name');
            $table->string('country_of_birth', 2)->nullable()->after('date_of_birth');
            $table->string('country_of_residence', 2)->nullable()->after('country_of_birth');
            $table->string('full_address', 500)->nullable()->after('country_of_residence');

            // Business
            $table->string('country_of_incorporation', 2)->nullable()->after('business_name');

            // Lock marker — non-null = locked from user edits.
            $table->timestamp('kyc_locked_at')->nullable()->after('kyc_status');
            $table->unsignedBigInteger('kyc_unlocked_by')->nullable()->after('kyc_locked_at');
            $table->timestamp('kyc_unlocked_at')->nullable()->after('kyc_unlocked_by');
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn([
                'date_of_birth',
                'country_of_birth',
                'country_of_residence',
                'full_address',
                'country_of_incorporation',
                'kyc_locked_at',
                'kyc_unlocked_by',
                'kyc_unlocked_at',
            ]);
        });
    }
};
