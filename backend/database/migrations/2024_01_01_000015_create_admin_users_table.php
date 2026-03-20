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
        Schema::create('admin_users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('email', 255)->unique();
            $table->string('password_hash', 255);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->enum('role', ['super_admin', 'admin', 'manager', 'staff'])->default('staff');
            $table->json('permissions')->nullable(); // Specific permissions
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->boolean('require_password_change')->default(false);
            $table->timestamp('password_changed_at')->nullable();
            $table->string('remember_token')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('username');
            $table->index('email');
            $table->index(['role', 'status']);
            $table->index('status');
            $table->index('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_users');
    }
};