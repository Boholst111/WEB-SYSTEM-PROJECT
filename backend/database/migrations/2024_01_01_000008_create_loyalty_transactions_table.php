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
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('preorder_id')->nullable();
            $table->enum('transaction_type', [
                'earned', 
                'redeemed', 
                'expired', 
                'bonus', 
                'adjustment',
                'refund',
                'tier_bonus'
            ]);
            $table->decimal('amount', 8, 2); // Can be negative for redemptions
            $table->decimal('balance_before', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->string('description', 255)->nullable();
            $table->string('reference_id', 100)->nullable(); // External reference
            $table->timestamp('expires_at')->nullable(); // For earned credits
            $table->boolean('is_expired')->default(false);
            $table->json('metadata')->nullable(); // Additional transaction data
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
            $table->foreign('preorder_id')->references('id')->on('preorders')->onDelete('set null');

            // Indexes for ledger performance
            $table->index(['user_id', 'transaction_type']);
            $table->index(['user_id', 'created_at']);
            $table->index('order_id');
            $table->index('preorder_id');
            $table->index('expires_at');
            $table->index(['expires_at', 'is_expired']);
            $table->index('reference_id');
            $table->index(['transaction_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
    }
};