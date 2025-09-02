<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessHour extends Model
{
    protected $table = 'business_hours';

    protected $fillable = [
        'store_id',
        'weekday',
        'open_at',
        'close_at',
        'is_closed',
    ];

    protected $casts = [
        'is_closed' => 'boolean',
        'open_at'   => 'datetime:H:i',
        'close_at'  => 'datetime:H:i',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

}
