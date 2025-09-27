<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'value' => 'string',
    ];

    /**
     * Fetch the raw value for a given key.
     */
    public static function valueOf(string $key, mixed $default = null): mixed
    {
        $value = static::query()->where('key', $key)->value('value');

        return $value !== null ? $value : $default;
    }

    /**
     * Convenience helper to fetch a float value from settings.
     */
    public static function floatValue(string $key, float $default = 0.0): float
    {
        $value = static::valueOf($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return (float) $value;
    }
}
