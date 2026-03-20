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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('category_id');
            $table->string('scale', 20)->nullable(); // e.g., "1:64", "1:43", "1:18"
            $table->string('material', 50)->nullable(); // e.g., "diecast", "plastic", "resin"
            $table->json('features')->nullable(); // e.g., ["opening_doors", "detailed_interior", "rubber_tires"]
            $table->boolean('is_chase_variant')->default(false);
            $table->decimal('base_price', 10, 2);
            $table->decimal('current_price', 10, 2);
            $table->integer('stock_quantity')->default(0);
            $table->boolean('is_preorder')->default(false);
            $table->date('preorder_date')->nullable();
            $table->date('estimated_arrival_date')->nullable();
            $table->enum('status', ['active', 'inactive', 'discontinued'])->default('active');
            $table->json('images')->nullable(); // Array of image URLs
            $table->json('specifications')->nullable(); // Additional product specs
            $table->decimal('weight', 8, 2)->nullable(); // Product weight in grams
            $table->json('dimensions')->nullable(); // Length, width, height
            $table->integer('minimum_age')->nullable(); // Recommended minimum age
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');

            // Complex indexes for filtering performance
            $table->index(['brand_id', 'category_id']);
            $table->index(['scale', 'material']);
            $table->index(['is_preorder', 'preorder_date']);
            $table->index('is_chase_variant');
            $table->index(['status', 'stock_quantity']);
            $table->index(['current_price', 'status']);
            $table->index(['category_id', 'status', 'stock_quantity']);
            $table->index(['brand_id', 'status', 'stock_quantity']);
            $table->index('estimated_arrival_date');
            
            // Full-text search index (only for MySQL)
            if (config('database.default') !== 'sqlite') {
                $table->fullText(['name', 'description'], 'products_search_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};