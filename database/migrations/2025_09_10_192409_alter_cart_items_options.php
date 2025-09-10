<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('cart_items', function (Blueprint $t) {
            // If your MySQL supports JSON:
            // $t->json('options')->nullable()->change();
            // Otherwise use TEXT:
            $t->text('options')->nullable()->change();
        });
    }
    public function down(): void {
        // no-op or revert to previous type if you know it
    }
};

