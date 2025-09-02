<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use SoftDeletes;

    protected $table = 'stores';

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'logo_path',
        'brand_color',
        'description',
        'address',
        'lat',
        'lng',
        'is_active',
        'delivery_settings',
        'country',
        'city',
    ];

    protected $casts = [
        'lat'               => 'decimal:7',
        'lng'               => 'decimal:7',
        'is_active'         => 'boolean',
        'delivery_settings' => 'array',     // stored as JSON, returned as array
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
        'deleted_at'        => 'datetime',
    ];
    public function owner()        { return $this->belongsTo(User::class, 'owner_id'); }
public function users()        { return $this->hasMany(User::class); } // if you link drivers via users.store_id
public function businessHours(){ return $this->hasMany(BusinessHour::class); }
public function products()     { return $this->hasMany(Product::class); }
public function categories()   { return $this->hasMany(Category::class); }
public function brands()       { return $this->hasMany(Brand::class); }
public function orders()       { return $this->hasMany(Order::class); }

}
