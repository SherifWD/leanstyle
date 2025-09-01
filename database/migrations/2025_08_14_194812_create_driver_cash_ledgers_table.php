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
        Schema::create('driver_cash_ledgers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('driver_id');
            $table->unsignedBigInteger('order_id')->nullable(); // link when entry is per-order
            $table->enum('type', ['collect','remit','adjustment']); // collect=+ve, remit=-ve
            $table->decimal('amount', 12, 2);
            $table->text('note')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamps();

            $table->foreign('driver_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->index(['driver_id','effective_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_cash_ledgers');
    }
};
