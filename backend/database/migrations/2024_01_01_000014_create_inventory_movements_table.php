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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->enum('movement_type', [
                'purchase', // Stock increase from supplier
                'restock', // Stock increase from restock
                'sale', // Stock decrease from customer order
                'return', // Stock increase from customer return
                'adjustment', // Manual stock adjustment
                'damage', // Stock decrease due to damage
                'reservation', // Temporary stock hold
                'release', // Release reserved stock
                'purchase_order' // Purchase order created
            ]);
            $table->integer('quantity_change'); // Can be negative
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->string('reference_type', 50)->nullable(); // order, preorder, adjustment
            $table->unsignedBigInteger('reference_id')->nullable(); // ID of related record
            $table->string('reason', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable(); // Admin user who made change
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            // Indexes for inventory tracking
            $table->index(['product_id', 'created_at']);
            $table->index('movement_type');
            $table->index(['reference_type', 'reference_id']);
            $table->index('created_by');
            $table->index(['product_id', 'movement_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};