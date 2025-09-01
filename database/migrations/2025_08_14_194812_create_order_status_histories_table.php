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
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->enum('from_status', ['pending','preparing','ready','assigned','picked','out_for_delivery','delivered','rejected','cancelled'])->nullable();
            $table->enum('to_status',   ['pending','preparing','ready','assigned','picked','out_for_delivery','delivered','rejected','cancelled']);
            $table->unsignedBigInteger('changed_by'); // user id (admin/owner/driver/system)
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('changed_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['order_id','to_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
    }
};
