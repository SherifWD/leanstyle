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
        Schema::create('coupon_redemptions', function (Blueprint $table) {
           $table->id();
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('user_id')->nullable();  // or customer_id, depending on auth
            $table->unsignedBigInteger('order_id')->nullable(); // set after conversion
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->timestamp('redeemed_at')->useCurrent();
            $table->timestamps();

            $table->foreign('coupon_id')->references('id')->on('coupons')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->index(['coupon_id','user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
    }
};
