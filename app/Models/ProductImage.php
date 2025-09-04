<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $guarded = [];

    public function product()       { return $this->belongsTo(Product::class); }
public function variant()       { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }

 public function getImageAttribute($val)
    {
        return ($val !== null) ? asset('/'.$val) : "";

    }
}
