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
        Schema::table('driver_cash_ledgers', function (Blueprint $table) {
            $table->foreignId('store_id')
                ->nullable()
                ->after('order_id')
                ->constrained('stores')
                ->nullOnDelete();

            $table->decimal('order_total', 12, 2)->default(0)->after('amount');
            $table->decimal('delivery_fee', 12, 2)->default(0)->after('order_total');
            $table->decimal('tax_fee', 12, 2)->default(0)->after('delivery_fee');
            $table->decimal('driver_earnings', 12, 2)->default(0)->after('tax_fee');
            $table->decimal('store_amount', 12, 2)->default(0)->after('driver_earnings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_cash_ledgers', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropColumn([
                'store_id',
                'order_total',
                'delivery_fee',
                'tax_fee',
                'driver_earnings',
                'store_amount',
            ]);
        });
    }
};
