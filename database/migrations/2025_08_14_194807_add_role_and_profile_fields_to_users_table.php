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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin','shop_owner','delivery_boy'])->default('shop_owner')->after('email');
            $table->string('phone')->nullable()->after('password');
            $table->boolean('is_blocked')->default(false)->after('remember_token');
            $table->timestamp('blocked_at')->nullable()->after('is_blocked');
            // For delivery boy tracking
            $table->boolean('is_available')->default(false)->after('blocked_at');
            $table->unsignedBigInteger('store_id')->nullable()->after('is_available'); // shop_owner’s store (optional)
            $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropColumn(['role','phone','is_blocked','blocked_at','is_available','store_id']);
        });
    }
};
