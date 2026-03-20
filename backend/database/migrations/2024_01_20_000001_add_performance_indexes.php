<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration adds optimized composite indexes for complex filtering queries
     * commonly used during Drop Day traffic and product browsing.
     */
    public function up(): void
    {
        // Add composite indexes for complex product filtering queries
        Schema::table('products', function (Blueprint $table) {
            // Multi-filter query optimization (scale + material + status)
            $table->index(['scale', 'material', 'status'], 'idx_scale_material_status');
            
            // Chase variant filtering with availability
            $table->index(['is_chase_variant', 'status', 'stock_quantity'], 'idx_chase_status_stock');
            
            // Pre-order filtering with date range
            $table->index(['is_preorder', 'status', 'estimated_arrival_date'], 'idx_preorder_status_arrival');
            
            // Price range filtering with status
            $table->index(['status', 'current_price'], 'idx_status_price');
        });

        // Add composite indexes for order queries
        Schema::table('orders', function (Blueprint $table) {
            // User order history with date filtering
            $table->index(['user_id', 'created_at', 'status'], 'idx_user_date_status');
            
            // Admin order management queries
            $table->index(['status', 'payment_status', 'created_at'], 'idx_status_payment_date');
        });

        // Add composite indexes for pre-order queries
        Schema::table('preorders', function (Blueprint $table) {
            // Payment reminder queries
            $table->index(['status', 'full_payment_due_date', 'notification_sent'], 'idx_payment_reminder');
            
            // Arrival notification queries
            $table->index(['status', 'actual_arrival_date', 'notification_sent'], 'idx_arrival_notification');
        });

        // Add composite indexes for loyalty transaction queries
        Schema::table('loyalty_transactions', function (Blueprint $table) {
            // User transaction history with type filtering
            $table->index(['user_id', 'transaction_type', 'created_at'], 'idx_user_type_date');
            
            // Expiration processing queries
            $table->index(['expires_at', 'is_expired', 'transaction_type'], 'idx_expiration_processing');
        });

        // Optimize MySQL query cache and buffer settings
        if (config('database.default') === 'mysql') {
            // These settings improve query performance for read-heavy workloads
            DB::statement('SET GLOBAL query_cache_size = 67108864'); // 64MB
            DB::statement('SET GLOBAL query_cache_type = 1');
            DB::statement('SET GLOBAL query_cache_limit = 2097152'); // 2MB
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_scale_material_status');
            $table->dropIndex('idx_chase_status_stock');
            $table->dropIndex('idx_preorder_status_arrival');
            $table->dropIndex('idx_status_price');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_user_date_status');
            $table->dropIndex('idx_status_payment_date');
        });

        Schema::table('preorders', function (Blueprint $table) {
            $table->dropIndex('idx_payment_reminder');
            $table->dropIndex('idx_arrival_notification');
        });

        Schema::table('loyalty_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_user_type_date');
            $table->dropIndex('idx_expiration_processing');
        });
    }
};
