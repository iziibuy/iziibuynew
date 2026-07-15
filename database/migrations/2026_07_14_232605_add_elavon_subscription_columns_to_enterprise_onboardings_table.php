<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enterprise_onboardings', function (Blueprint $table) {
            $table->string('subscriptionMethod')->default('quickpay')->after('paymentMethod');
            $table->string('shopperId')->nullable()->after('subscriptionMethod');
            $table->string('elavon_initial_transaction_id')->nullable()->after('shopperId');
        });
    }

    public function down(): void
    {
        Schema::table('enterprise_onboardings', function (Blueprint $table) {
            $table->dropColumn(['subscriptionMethod', 'shopperId', 'elavon_initial_transaction_id']);
        });
    }
};
