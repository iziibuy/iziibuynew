<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_method_accesses', function (Blueprint $table) {
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->boolean('trial_used')->default(false);
            $table->timestamp('trial_charged_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_method_accesses', function (Blueprint $table) {
            $table->dropColumn(['trial_started_at', 'trial_ends_at', 'trial_used', 'trial_charged_at']);
        });
    }
};
