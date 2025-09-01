<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $guarded = [];

    public function user()        { return $this->belongsTo(User::class); }
public function store()       { return $this->belongsTo(Store::class); }
public function items()       { return $this->hasMany(CartItem::class); }
public function address()     { return $this->belongsTo(CustomerAddress::class, 'customer_address_id'); }
public function couponRedemptions() { return $this->hasMany(CouponRedemption::class); } // if you allow applying coupon pre-order

}
