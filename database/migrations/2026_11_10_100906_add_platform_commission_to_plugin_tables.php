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
        Schema::table('payment_method_accesses', function (Blueprint $table) {
            $table->decimal('platform_commission', 5, 2)->default(0);
        });

        foreach (['external_orders', 'external_bookings'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->decimal('platform_percentage', 5, 2)->default(0);
                $table->decimal('platform_amount', 12, 2)->default(0);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_method_accesses', function (Blueprint $table) {
            $table->dropColumn('platform_commission');
        });

        foreach (['external_orders', 'external_bookings'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['platform_percentage', 'platform_amount']);
            });
        }
    }
};
