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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('type', ['percent','fixed']);
            $table->decimal('value', 12, 2);              // 10 -> 10% if percent, or 10.00 if fixed
            $table->decimal('max_discount', 12, 2)->nullable();
            $table->decimal('min_subtotal', 12, 2)->default(0);
            $table->unsignedInteger('max_redemptions')->nullable();   // global cap
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
