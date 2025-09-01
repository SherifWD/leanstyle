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
        Schema::create('order_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique(); // one active assignment per order
            $table->unsignedBigInteger('driver_id'); // users.id with role delivery_boy
            $table->unsignedBigInteger('assigned_by'); // admin or shop_owner
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();     // left store
            $table->timestamp('picked_at')->nullable();      // picked from store
            $table->timestamp('out_for_delivery_at')->nullable();
            $table->timestamp('completed_at')->nullable();   // delivered
            $table->timestamp('rejected_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('driver_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('assigned_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['driver_id','assigned_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_assignments');
    }
};
