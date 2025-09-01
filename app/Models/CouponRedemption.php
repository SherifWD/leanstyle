<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CouponRedemption extends Model
{
    protected $guarded = [];

    public function coupon() { return $this->belongsTo(Coupon::class); }
public function user()   { return $this->belongsTo(User::class); }
public function order()  { return $this->belongsTo(Order::class); }

}
