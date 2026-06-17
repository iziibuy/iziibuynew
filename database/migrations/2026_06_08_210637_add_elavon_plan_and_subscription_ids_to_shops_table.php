<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('elavon_plan_id')->nullable()->after('subscription_id');
            $table->string('elavon_subscription_id')->nullable()->after('elavon_plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['elavon_plan_id', 'elavon_subscription_id']);
        });
    }
};
