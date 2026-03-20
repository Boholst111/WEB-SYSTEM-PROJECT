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
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('allow_email_marketing')->default(false);
            $table->boolean('allow_sms_marketing')->default(false);
            $table->boolean('allow_order_updates')->default(true);
            $table->boolean('allow_preorder_notifications')->default(true);
            $table->boolean('allow_loyalty_notifications')->default(true);
            $table->boolean('allow_security_alerts')->default(true);
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
