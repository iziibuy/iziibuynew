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
        foreach (['shops', 'payment_method_accesses'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->boolean('wallet_google_pay')->default(false);
                $table->boolean('wallet_apple_pay')->default(false);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['shops', 'payment_method_accesses'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['wallet_google_pay', 'wallet_apple_pay']);
            });
        }
    }
};
