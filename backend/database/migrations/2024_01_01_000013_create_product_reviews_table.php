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
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('order_id')->nullable(); // Verify purchase
            $table->integer('rating'); // 1-5 stars
            $table->string('title', 255)->nullable();
            $table->text('review_text')->nullable();
            $table->json('images')->nullable(); // User uploaded images
            $table->boolean('is_verified_purchase')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->integer('helpful_votes')->default(0);
            $table->integer('total_votes')->default(0);
            $table->integer('helpful_count')->default(0);
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index(['product_id', 'is_approved']);
            $table->index(['user_id', 'product_id']);
            $table->index('rating');
            $table->index('is_verified_purchase');
            $table->index(['is_approved', 'created_at']);
            
            // Unique constraint - one review per user per product
            $table->unique(['user_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};