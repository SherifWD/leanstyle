<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];

    public function store()          { return $this->belongsTo(Store::class); }
public function customer()       { return $this->belongsTo(Customer::class); }
public function items()          { return $this->hasMany(OrderItem::class); }
public function statusHistories(){ return $this->hasMany(OrderStatusHistory::class); }
public function assignment()     { return $this->hasOne(OrderAssignment::class); }
public function driver()         { return $this->hasOneThrough(User::class, OrderAssignment::class, 'order_id', 'id', 'id', 'driver_id'); }
public function couponRedemptions(){ return $this->hasMany(CouponRedemption::class); }
public function cashLedgerEntries(){ return $this->hasMany(DriverCashLedger::class); } // when linked per order
public function address(){ return $this->belongsTo(CustomerAddress::class,'address_id'); } // when linked per order

}
