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
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cart_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variant_id')->nullable();

            // Snapshots to keep UI stable if product changes later
            $table->string('name');                  // product name at add time
            $table->json('options')->nullable();     // size/color/etc.
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('unit_price', 12, 2);    // price at add time (pre-discount)
            $table->decimal('discount',   12, 2)->default(0); // per-unit discount
            $table->decimal('line_total', 12, 2);    // (unit_price - discount) * qty

            $table->timestamps();

            $table->foreign('cart_id')->references('id')->on('carts')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->nullOnDelete();

            $table->index(['cart_id','product_id','product_variant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
