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
        Schema::create('driver_remittances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('driver_id');
            $table->unsignedBigInteger('received_by'); // admin user
            $table->decimal('amount', 12, 2);
            $table->string('reference')->nullable(); // receipt no.
            $table->timestamp('received_at')->nullable();
            $table->json('details')->nullable(); // optional breakdown
            $table->timestamps();

            $table->foreign('driver_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('received_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['driver_id','received_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_remittances');
    }
};
