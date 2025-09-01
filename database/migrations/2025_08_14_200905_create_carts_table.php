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
        Schema::create('carts', function (Blueprint $table) {
            $table->id();

            // Who owns this cart?
            $table->unsignedBigInteger('user_id')->nullable();   // logged-in user
            $table->string('session_id', 64)->nullable()->index(); // guest session key
            $table->unsignedBigInteger('store_id')->nullable();  // multi-store (optional)

            // Selected address & payment for checkout summary (can be null until chosen)
            $table->unsignedBigInteger('customer_address_id')->nullable();
            $table->string('payment_method')->nullable(); // e.g. 'cod', 'card'

            // Order notes entered in checkout
            $table->text('notes')->nullable();

            // Money snapshot (recomputed on change; used to show "ملخص التكلفة")
            $table->decimal('subtotal',      12, 2)->default(0);
            $table->decimal('discount_total',12, 2)->default(0); // coupons / line discounts
            $table->decimal('tax_total',     12, 2)->default(0);
            $table->decimal('delivery_fee',  12, 2)->default(0);
            $table->decimal('grand_total',   12, 2)->default(0);

            // Lifecycle
            $table->enum('status', ['active','converted','abandoned'])->default('active')->index();
            $table->timestamp('expires_at')->nullable(); // auto-cleanup abandoned carts

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();
            $table->foreign('customer_address_id')->references('id')->on('customer_addresses')->nullOnDelete();

            $table->index(['user_id','session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
