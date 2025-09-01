<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverRemittance extends Model
{
    protected $guarded = [];

    public function driver()     { return $this->belongsTo(User::class, 'driver_id'); }
public function receivedBy() { return $this->belongsTo(User::class, 'received_by'); }

}
