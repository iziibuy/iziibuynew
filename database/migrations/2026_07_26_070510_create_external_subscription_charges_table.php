<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_subscription_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_subscription_id')->constrained('external_subscriptions')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('NOK');
            $table->boolean('status')->default(false);
            $table->string('type')->default('renewal'); // signup|renewal
            $table->string('elavon_transaction_id')->nullable();
            $table->string('surfboard_transaction_id')->nullable();
            $table->text('payment_details')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->index(['external_subscription_id', 'type'], 'ext_sub_charges_sub_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_subscription_charges');
    }
};
