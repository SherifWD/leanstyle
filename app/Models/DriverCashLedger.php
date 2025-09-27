<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverCashLedger extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount'          => 'decimal:2',
        'order_total'     => 'decimal:2',
        'delivery_fee'    => 'decimal:2',
        'tax_fee'         => 'decimal:2',
        'driver_earnings' => 'decimal:2',
        'store_amount'    => 'decimal:2',
        'effective_at'    => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
