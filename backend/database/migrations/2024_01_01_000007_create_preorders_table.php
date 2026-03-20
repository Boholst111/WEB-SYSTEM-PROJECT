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
        Schema::create('preorders', function (Blueprint $table) {
            $table->id();
            $table->string('preorder_number', 50)->unique();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('user_id');
            $table->integer('quantity')->default(1);
            $table->decimal('deposit_amount', 8, 2);
            $table->decimal('remaining_amount', 8, 2);
            $table->decimal('total_amount', 10, 2); // deposit + remaining
            $table->timestamp('deposit_paid_at')->nullable();
            $table->date('full_payment_due_date')->nullable();
            $table->enum('status', [
                'deposit_pending',
                'deposit_paid', 
                'ready_for_payment',
                'payment_completed',
                'shipped',
                'delivered',
                'cancelled',
                'expired'
            ])->default('deposit_pending');
            $table->date('estimated_arrival_date')->nullable();
            $table->date('actual_arrival_date')->nullable();
            $table->string('payment_method')->nullable();
            $table->json('shipping_address')->nullable();
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->boolean('notification_sent')->default(false);
            $table->timestamp('payment_reminder_sent_at')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes for performance
            $table->index(['user_id', 'status']);
            $table->index(['product_id', 'status']);
            $table->index('preorder_number');
            $table->index('estimated_arrival_date');
            $table->index('full_payment_due_date');
            $table->index(['status', 'estimated_arrival_date']);
            $table->index(['status', 'full_payment_due_date']);
            $table->index(['notification_sent', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preorders');
    }
};