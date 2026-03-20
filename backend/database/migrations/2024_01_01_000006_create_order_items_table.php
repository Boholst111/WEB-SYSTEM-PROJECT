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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->string('product_sku', 50); // Store SKU for historical reference
            $table->string('product_name', 255); // Store name for historical reference
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2); // Price at time of purchase
            $table->decimal('total_price', 10, 2); // quantity * unit_price
            $table->json('product_snapshot')->nullable(); // Store product details at time of purchase
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');

            // Indexes
            $table->index('order_id');
            $table->index('product_id');
            $table->index(['order_id', 'product_id']);
            $table->index('product_sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};