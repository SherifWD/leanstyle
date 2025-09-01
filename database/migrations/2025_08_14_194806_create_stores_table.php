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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id'); // users.id with role = shop_owner
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->string('brand_color')->nullable();
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('delivery_settings')->nullable(); // min_order, fee, radius, etc.
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['owner_id','is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void { Schema::dropIfExists('stores'); }
};
