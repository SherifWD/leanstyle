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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable(); // global or per-store
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();
            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
        
        });
    }

    /**
     * Reverse the migrations.
     */
        public function down(): void { Schema::dropIfExists('categories'); }

};
