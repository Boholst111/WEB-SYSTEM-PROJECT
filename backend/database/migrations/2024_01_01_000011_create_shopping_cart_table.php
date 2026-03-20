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
        Schema::create('shopping_cart', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('session_id', 255)->nullable(); // For guest users
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity');
            $table->decimal('price', 10, 2); // Price when added to cart
            $table->json('product_options')->nullable(); // Any customizations
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');

            // Indexes
            $table->index(['user_id', 'product_id']);
            $table->index('session_id');
            $table->index('product_id');
            $table->index('created_at'); // For cleanup of old cart items
            
            // Unique constraint to prevent duplicate items
            $table->unique(['user_id', 'product_id'], 'unique_user_product');
            $table->unique(['session_id', 'product_id'], 'unique_session_product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopping_cart');
    }
};