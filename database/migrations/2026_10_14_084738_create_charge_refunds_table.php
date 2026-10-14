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
        Schema::create('charge_refunds', function (Blueprint $table) {
            $table->id();
            $table->string('ledger_key')->unique();
            $table->morphs('refundable');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('NOK');
            $table->string('provider')->default('elavon');
            $table->string('provider_operation');
            $table->string('provider_transaction_id')->nullable();
            $table->string('original_transaction_id');
            $table->text('reason');
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('rollback')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charge_refunds');
    }
};
