<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $guarded = [];

    public function addresses() { return $this->hasMany(CustomerAddress::class); }
public function orders()    { return $this->hasMany(Order::class); }

}
