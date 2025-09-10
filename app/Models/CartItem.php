<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $guarded = [];
protected $casts = [
        'options'    => 'array',   // <-- IMPORTANT (will json_encode on save)
        'qty'        => 'integer',
        'unit_price' => 'float',
        'discount'   => 'float',
        'line_total' => 'float',
    ];
    public function cart()           { return $this->belongsTo(Cart::class); }
public function product()        { return $this->belongsTo(Product::class); }
public function productVariant() { return $this->belongsTo(ProductVariant::class); }

}
