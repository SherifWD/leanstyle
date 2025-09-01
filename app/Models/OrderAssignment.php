<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderAssignment extends Model
{
    protected $guarded = [];

    public function order()      { return $this->belongsTo(Order::class); }
public function driver()     { return $this->belongsTo(User::class, 'driver_id'); }
public function assignedBy() { return $this->belongsTo(User::class, 'assigned_by'); }

}
