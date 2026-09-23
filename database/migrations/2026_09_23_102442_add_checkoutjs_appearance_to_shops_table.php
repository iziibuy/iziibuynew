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
        Schema::table('shops', function (Blueprint $table) {
            $table->string('elavon_link_mode', 32)
                ->default('hosted')
                ->after('paymentMethod');
            $table->json('checkoutjs_appearance')->nullable()->after('elavon_link_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['elavon_link_mode', 'checkoutjs_appearance']);
        });
    }
};
