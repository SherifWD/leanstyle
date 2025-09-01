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
        Schema::create('driver_availabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('driver_id'); // users.id
            $table->boolean('is_available');
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->foreign('driver_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['driver_id','changed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_availabilities');
    }
};
