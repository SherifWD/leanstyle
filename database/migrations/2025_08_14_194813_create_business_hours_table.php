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
        Schema::create('business_hours', function (Blueprint $table) {
           $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedTinyInteger('weekday'); // 0=Sun .. 6=Sat
            $table->time('open_at')->nullable();
            $table->time('close_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(['store_id','weekday']);
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
        
        });
    }

    /**
     * Reverse the migrations.
     */
        public function down(): void { Schema::dropIfExists('business_hours'); }

};
