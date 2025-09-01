<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverCashLedger extends Model
{
    protected $guarded = [];

    public function driver() { return $this->belongsTo(User::class, 'driver_id'); }
public function order()  { return $this->belongsTo(Order::class); }

}
