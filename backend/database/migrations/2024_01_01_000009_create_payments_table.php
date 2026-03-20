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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('preorder_id')->nullable();
            $table->enum('payment_method', ['gcash', 'maya', 'bank_transfer', 'credit_card']);
            $table->enum('gateway', ['gcash', 'maya', 'bank_transfer', 'paymongo', 'xendit']);
            $table->string('gateway_transaction_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('PHP');
            $table->enum('status', [
                'pending',
                'processing', 
                'completed',
                'failed',
                'cancelled',
                'refunded'
            ])->default('pending');
            $table->json('gateway_response')->nullable(); // Store gateway response
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
            $table->foreign('preorder_id')->references('id')->on('preorders')->onDelete('set null');

            // Indexes
            $table->index(['order_id', 'status']);
            $table->index('preorder_id');
            $table->index(['payment_method', 'status']);
            $table->index('gateway_transaction_id');
            $table->index(['status', 'created_at']);
            $table->index('processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};