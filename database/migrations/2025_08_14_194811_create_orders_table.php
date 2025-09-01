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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('customer_id');
            $table->enum('status', ['pending','preparing','ready','assigned','picked','out_for_delivery','delivered','rejected','cancelled'])
                  ->default('pending')->index();
            $table->string('order_code')->unique();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->string('payment_method')->default('cod'); // cash on delivery etc.
            $table->boolean('is_paid')->default(false);
            $table->text('notes')->nullable();

            // Delivery location snapshot (in case customer edits later)
            $table->string('ship_address')->nullable();
            $table->decimal('ship_lat', 10, 7)->nullable();
            $table->decimal('ship_lng', 10, 7)->nullable();

            // Timeline snapshots
            $table->timestamp('accepted_at')->nullable();  // shop owner accepts
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->index(['store_id','customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
