<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('image_path'); // stored under public/
            $table->string('link_url')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('position')->nullable(); // e.g., home, category, app_top
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->index(['is_active','position','category_id','sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};

