<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('merchant_payment_methods')
            ->select(['id', 'details'])
            ->orderBy('id')
            ->get();

        Schema::table('merchant_payment_methods', function (Blueprint $table) {
            $table->longText('details_enc')->nullable()->after('details');
        });

        foreach ($rows as $row) {
            $raw = $row->details;
            if ($raw === null || $raw === '') {
                continue;
            }
            DB::table('merchant_payment_methods')
                ->where('id', $row->id)
                ->update(['details_enc' => Crypt::encryptString($raw)]);
        }

        Schema::table('merchant_payment_methods', function (Blueprint $table) {
            $table->dropColumn('details');
        });

        Schema::table('merchant_payment_methods', function (Blueprint $table) {
            $table->renameColumn('details_enc', 'details');
        });
    }

    public function down(): void
    {
        $rows = DB::table('merchant_payment_methods')
            ->select(['id', 'details'])
            ->orderBy('id')
            ->get();

        Schema::table('merchant_payment_methods', function (Blueprint $table) {
            $table->json('details_plain')->nullable()->after('details');
        });

        foreach ($rows as $row) {
            if ($row->details === null || $row->details === '') {
                continue;
            }
            try {
                $plain = Crypt::decryptString($row->details);
            } catch (\Throwable $e) {
                continue;
            }
            DB::table('merchant_payment_methods')
                ->where('id', $row->id)
                ->update(['details_plain' => $plain]);
        }

        Schema::table('merchant_payment_methods', function (Blueprint $table) {
            $table->dropColumn('details');
        });

        Schema::table('merchant_payment_methods', function (Blueprint $table) {
            $table->renameColumn('details_plain', 'details');
        });
    }
};
