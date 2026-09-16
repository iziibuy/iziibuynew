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
        Schema::table('payment_apis', function (Blueprint $table) {
            $table->string('elavon_link_mode', 32)
                ->default('hosted')
                ->after('is_subscription');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_apis', function (Blueprint $table) {
            $table->dropColumn('elavon_link_mode');
        });
    }
};
