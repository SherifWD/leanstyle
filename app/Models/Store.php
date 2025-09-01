<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $guarded = [];
    public function owner()        { return $this->belongsTo(User::class, 'owner_id'); }
public function users()        { return $this->hasMany(User::class); } // if you link drivers via users.store_id
public function businessHours(){ return $this->hasMany(BusinessHour::class); }
public function products()     { return $this->hasMany(Product::class); }
public function categories()   { return $this->hasMany(Category::class); }
public function brands()       { return $this->hasMany(Brand::class); }
public function orders()       { return $this->hasMany(Order::class); }

}
