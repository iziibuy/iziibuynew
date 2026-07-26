<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->ulid('uuid')->unique();
            $table->foreignId('payment_method_access_id')->constrained('payment_method_accesses')->cascadeOnDelete();
            $table->foreignId('api_id')->constrained('payment_apis')->cascadeOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_country')->nullable();
            $table->string('customer_address')->nullable();
            $table->string('customer_post_code')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('NOK');
            $table->unsignedInteger('interval_days');
            $table->string('description')->nullable();
            $table->string('orderId')->nullable();
            $table->string('taxValue')->nullable();
            $table->string('taxTotal')->nullable();
            $table->string('status')->default('PENDING'); // PENDING|ACTIVE|PAST_DUE|CANCELED|FAILED
            $table->string('payment_method')->default('elavon');
            $table->string('payment_id')->nullable(); // HPP session / order id
            $table->string('payment_url')->nullable();
            $table->string('stored_card_id')->nullable();
            $table->string('shopper_id')->nullable();
            $table->string('initial_transaction_id')->nullable();
            $table->string('surfboard_token')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('next_charge_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->text('response')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_charge_at']);
            $table->index(['api_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_subscriptions');
    }
};
