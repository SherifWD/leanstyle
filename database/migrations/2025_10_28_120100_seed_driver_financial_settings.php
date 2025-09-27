<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'driver_delivery_fee'],
            ['value' => '0']
        );

        Setting::query()->updateOrCreate(
            ['key' => 'driver_tax_fee'],
            ['value' => '0']
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Setting::query()->whereIn('key', ['driver_delivery_fee', 'driver_tax_fee'])->delete();
    }
};
