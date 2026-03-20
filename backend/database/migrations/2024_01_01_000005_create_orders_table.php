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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->unsignedBigInteger('user_id');
            $table->enum('status', [
                'pending', 
                'confirmed', 
                'processing', 
                'shipped', 
                'delivered', 
                'cancelled',
                'refunded'
            ])->default('pending');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('credits_used', 10, 2)->default(0.00);
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->decimal('shipping_fee', 8, 2)->default(0.00);
            $table->decimal('tax_amount', 8, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2);
            $table->string('payment_method', 50)->nullable();
            $table->enum('payment_status', [
                'pending', 
                'paid', 
                'failed', 
                'refunded', 
                'partially_refunded'
            ])->default('pending');
            $table->json('shipping_address'); // Complete shipping address
            $table->json('billing_address')->nullable(); // Billing address if different
            $table->string('tracking_number')->nullable();
            $table->string('courier_service')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes for performance
            $table->index(['user_id', 'status']);
            $table->index('order_number');
            $table->index('payment_status');
            $table->index('status');
            $table->index('created_at');
            $table->index(['status', 'created_at']);
            $table->index('tracking_number');
            $table->index(['shipped_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};