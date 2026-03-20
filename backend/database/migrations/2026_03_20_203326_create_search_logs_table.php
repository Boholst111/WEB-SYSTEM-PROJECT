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
        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('query', 255);
            $table->integer('results_count')->default(0);
            $table->foreignId('clicked_product_id')->nullable()->constrained('products')->onDelete('set null');
            $table->timestamp('searched_at');
            
            $table->index(['query', 'searched_at']);
            $table->index('user_id');
            $table->index('searched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_logs');
    }
};
