<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = [];

    public function store()        { return $this->belongsTo(Store::class); }
public function category()     { return $this->belongsTo(Category::class); }
public function brand()        { return $this->belongsTo(Brand::class); }
public function variants()     { return $this->hasMany(ProductVariant::class); }
public function images()       { return $this->hasMany(ProductImage::class); }
public function orderItems()   { return $this->hasMany(OrderItem::class); }
public function views()        { return $this->hasMany(ProductView::class); }


}
