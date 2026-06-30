<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enterprises', function (Blueprint $table): void {
            if (! Schema::hasColumn('enterprises', 'elavon_shopper_id')) {
                $table->string('elavon_shopper_id')->nullable()->after('subscription_id');
            }
            if (! Schema::hasColumn('enterprises', 'payment_provider')) {
                $table->string('payment_provider', 32)->default('elavon')->after('elavon_shopper_id');
            }
        });

        Schema::table('subscription_charges', function (Blueprint $table): void {
            if (! Schema::hasColumn('subscription_charges', 'elavon_transaction_id')) {
                $table->string('elavon_transaction_id')->nullable()->after('quickpay_order_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enterprises', function (Blueprint $table): void {
            if (Schema::hasColumn('enterprises', 'elavon_shopper_id')) {
                $table->dropColumn('elavon_shopper_id');
            }
            if (Schema::hasColumn('enterprises', 'payment_provider')) {
                $table->dropColumn('payment_provider');
            }
        });

        Schema::table('subscription_charges', function (Blueprint $table): void {
            if (Schema::hasColumn('subscription_charges', 'elavon_transaction_id')) {
                $table->dropColumn('elavon_transaction_id');
            }
        });
    }
};
